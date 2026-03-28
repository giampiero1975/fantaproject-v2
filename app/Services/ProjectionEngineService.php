<?php

namespace App\Services;

use App\Models\Player;
use App\Models\UserLeagueProfile;
use App\Models\PlayerFbrefStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Psr\Log\LoggerInterface;

class ProjectionEngineService
{
    private ?LoggerInterface $logger;
    private FantasyPointCalculatorService $fantasyPointCalculator;
    
    private static array $percentileCache = [];
    
    public function __construct(FantasyPointCalculatorService $fantasyPointCalculator)
    {
        $this->fantasyPointCalculator = $fantasyPointCalculator;
        $this->logger = null;
    }
    
    private function isDebug(): bool
    {
        return config('projection_settings.debug_enabled', false);
    }
    
    private function debugLog(string $message, array $context = []): void
    {
        if ($this->isDebug()) {
            if ($this->logger === null) {
                $this->logger = Log::channel('projections');
            }
            $this->logger->info($message, $context);
        }
    }
    
    // --- CARICAMENTO CONFIGURAZIONE PER RUOLO ---
    private function getRoleConfig(string $role): array
    {
        // Prova a caricare config/weights_ROLE.php (es. weights_P.php)
        $config = config("weights_{$role}");
        
        // Se non esiste, usa il vecchio global come fallback
        if (!$config) {
            return config('correlation_weights');
        }
        return $config;
    }
    // ---------------------------------------------
    
    private function preloadPercentiles(string $role): void
    {
        if (isset(self::$percentileCache[$role])) return;
        try {
            $rows = DB::table('config_percentile_ranks')->where('role', $role)->orderBy('threshold_value', 'asc')->get();
        } catch (\Exception $e) { $rows = collect([]); }
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->metric_name][] = ['p' => (float)$row->percentile_rank, 'v' => (float)$row->threshold_value];
        }
        self::$percentileCache[$role] = $grouped;
    }
    
    public function generatePlayerProjection(Player $player, ?int $targetSeasonYear = null): array
    {
        $targetLabel = $targetSeasonYear ?? 'FUTURE';
        $this->debugLog("=============== INIZIO PROIEZIONE: {$player->name} (Role: {$player->role}) ================");
        
        $playerRole = $player->role;
        if (!$playerRole) return [];
        
        // 1. CARICA LA CONFIGURAZIONE SPECIFICA
        $roleConfig = $this->getRoleConfig($playerRole);
        
        $this->preloadPercentiles($playerRole);
        
        $teamTier = 3;
        if ($targetSeasonYear) {
            $hStat = $player->historicalStats()->where('season_year', $targetSeasonYear)->with('team')->first();
            if ($hStat && $hStat->team && $hStat->team->tier > 0) $teamTier = $hStat->team->tier;
            elseif ($player->team && $player->team->tier > 0) $teamTier = $player->team->tier;
        } else {
            if ($player->team && $player->team->tier > 0) $teamTier = $player->team->tier;
        }
        $teamTier = (int)$teamTier; if ($teamTier < 1 || $teamTier > 5) $teamTier = 3;
        
        $minGames = config('projection_settings.min_games_for_reliable_avg_rating', 10);
        $query = $player->historicalStats()->orderBy('season_year', 'desc');
        if ($targetSeasonYear) $query->where('season_year', '<=', $targetSeasonYear);
        
        $rawHistoricalStats = $query->take(config('projection_settings.lookback_seasons', 4))->get();
        $enrichedStats = $this->enrichWithFbrefData($rawHistoricalStats, $player->id);
        $validSeasons = $enrichedStats->filter(fn($s) => $s->games_played >= $minGames);
        
        if ($validSeasons->isEmpty()) return [];
        
        $seasonWeights = $this->calculateSeasonWeights($validSeasons->pluck('season_year')->toArray());
        
        // Passiamo roleConfig anche qui per il base_rating corretto
        $weightedStats = $this->calculateWeightedAverages($validSeasons, $seasonWeights, $roleConfig);
        $weightedStats = $this->applyRegressionToMean($weightedStats, $playerRole);
        $ageModifier = $this->calculateAgeModifier($player, $targetSeasonYear);
        
        $offensiveMult = config("projection_settings.team_tier_multipliers_offensive.{$teamTier}", 1.0);
        $defensiveMult = config("projection_settings.team_tier_multipliers_defensive.{$teamTier}", 1.0);
        $teamMultipliers = ['offensive' => $offensiveMult, 'defensive' => $defensiveMult];
        
        $finalPerGameStats = $this->applyModulations($weightedStats, $ageModifier, $teamMultipliers);
        $historicalMv = $finalPerGameStats['mv'] ?? 6.0;
        
        // 2. USA PARAMETRI DAL FILE SPECIFICO
        $baseRating = $roleConfig['engine_parameters']['base_rating'] ?? 6.00;
        $retentionFactor = $roleConfig['engine_parameters']['tuning']['historical_retention'] ?? 0.40;
        
        $reliability = ($validSeasons->count() >= 2) ? 1.0 : 0.7;
        
        $archetypeResult = $this->calculateArchetypeModifier($finalPerGameStats, $playerRole, $roleConfig, $reliability, (int)$teamTier);
        $archetypeBonus = $archetypeResult['bonus'];
        
        $mvTierMultiplier = 1.0 + (($offensiveMult - 1.0) * 0.125);
        $tierBonus = $historicalMv * ($mvTierMultiplier - 1);
        $historicalDeviation = ($historicalMv - 6.0) * $retentionFactor;
        
        $mvProj = $baseRating + $historicalDeviation + $tierBonus + $archetypeBonus;
        
        $this->debugLog("CALCOLO MV (File: weights_{$playerRole}):", [
            'Base' => $baseRating,
            'Archetipi' => $archetypeBonus,
            'TOTALE' => $mvProj
        ]);
        
        $finalPerGameStats['mv'] = $mvProj;
        if (isset($finalPerGameStats['gol_subiti'])) $finalPerGameStats['goals_conceded'] = $finalPerGameStats['gol_subiti'];
        
        $estimatedPresences = $this->estimateGamesPlayed($player, $validSeasons, $ageModifier);
        $seasonalTotals = [];
        $rateStats = ['mv', 'avg_rating', 'fanta_mv', 'fanta_avg_rating', 'gk_save_percentage', 'clean_sheet_pct', 'passes_pct', 'aerials_won_pct', 'passes_pct_long', 'crosses_stopped_pct'];
        foreach ($finalPerGameStats as $key => $val) {
            if (in_array($key, $rateStats)) $seasonalTotals[$key] = $val;
            else $seasonalTotals[$key] = $val * $estimatedPresences;
        }
        
        $scoringRulesData = UserLeagueProfile::first()->scoring_rules ?? [];
        $scoringRules = is_array($scoringRulesData) ? $scoringRulesData : (json_decode($scoringRulesData, true) ?? []);
        $bonusMalusSum = $this->fantasyPointCalculator->calculateFantasyPoints($finalPerGameStats, $scoringRules, $playerRole);
        $fantaMedia = $mvProj + $bonusMalusSum;
        
        return array_merge($seasonalTotals, [
            'avg_rating_proj' => $mvProj,
            'fanta_mv_proj' => $fantaMedia,
            'games_played_proj' => $estimatedPresences,
            'total_fanta_points_proj' => $fantaMedia * $estimatedPresences,
            'active_archetypes' => $this->isDebug() ? $archetypeResult['active'] : null,
            'archetype_breakdown' => $this->isDebug() ? $archetypeResult['breakdown'] : null
        ]);
    }
    
    // Accetta Config specifica
    private function calculateArchetypeModifier(array $stats, string $role, array $config, float $reliability = 1.0, int $teamTier = 3): array
    {
        $result = ['bonus' => 0.0, 'active' => [], 'breakdown' => []];
        if ($role !== 'P' && $role !== 'D') return $result;
        if ($reliability < 0.5) return $result;
        
        $archetypeDefinitions = $config['archetypes'] ?? [];
        $finalWeights = $config['calculation_rules']['final_archetype_weights'] ?? [];
        $tuning = $config['engine_parameters']['tuning'] ?? [];
        
        // PARAMETRI LETTI DAL FILE SPECIFICO
        $activationThreshold = $tuning['archetype_activation_threshold'] ?? 55.0;
        $scalingFactor = $tuning['global_scaling_factor'] ?? 0.008;
        $impactCap = $config['engine_parameters']['impact_cap'] ?? 0.75;
        
        $activeArchetypes = [];
        $archetypeScores = [];
        $totalBonus = 0.0;
        $breakdown = [];
        
        foreach ($archetypeDefinitions as $archName => $archData) {
            $currentScore = 0.0;
            $metricsConfig = $archData['metrics'] ?? [];
            foreach ($metricsConfig as $metricKey => $weight) {
                $mappedKeys = $this->mapMetricKeys($metricKey);
                $dbKey = $mappedKeys['db'];
                $statKey = $mappedKeys['stat'];
                $rawValue = $stats[$statKey] ?? 0;
                
                $volumeMetrics = [
                    'stats_keeper_gk_saves', 'stats_defense_clearances', 'stats_gca_sca',
                    'stats_possession_touches_def_pen_area',
                    'stats_defense_tackles_won', 'stats_defense_interceptions',
                    'stats_defense_blocks', 'stats_misc_aerials_won',
                    'stats_goals_assists', 'stats_passing_passes_progressive_distance',
                    'stats_passing_passes_into_final_third'
                ];
                if (in_array($dbKey, $volumeMetrics)) $rawValue = $rawValue * 38;
                
                $percentile = $this->getPercentileRank($role, $dbKey, $rawValue);
                $currentScore += ($percentile * $weight);
            }
            $archetypeScores[$archName] = $currentScore;
            if ($currentScore >= $activationThreshold) $activeArchetypes[] = $archName;
            $breakdown[$archName] = $currentScore;
        }
        
        if (empty($activeArchetypes)) {
            $fallbackProfiles = $config['calculation_rules']['fallback_profiles'] ?? [];
            $activeArchetypes = $fallbackProfiles;
            $breakdown['FALLBACK_TRIGGERED'] = 1;
            foreach ($fallbackProfiles as $fb) {
                $realScore = $archetypeScores[$fb] ?? 50.0;
                $archetypeScores[$fb] = ($realScore + 50.0) / 2;
            }
        }
        
        foreach ($activeArchetypes as $archName) {
            $score = $archetypeScores[$archName] ?? 50.0;
            $weight = $finalWeights[$archName] ?? 0.0;
            $delta = $score - 50.0;
            $archBonus = $delta * $weight * $scalingFactor;
            $archBonus = $this->applyTierOverlap($archBonus, $teamTier, $tuning, $archName);
            $totalBonus += $archBonus;
        }
        
        $synergyBonus = $this->applyModulationRules($activeArchetypes, $config, $teamTier);
        $totalBonus += $synergyBonus;
        
        $finalBonus = max(-$impactCap, min($impactCap, $totalBonus));
        $result['bonus'] = $finalBonus * $reliability;
        $result['active'] = $activeArchetypes;
        $result['breakdown'] = $breakdown;
        
        return $result;
    }
    
    private function getPercentileRank(string $role, string $dbMetricKey, float $value): float {
        $thresholds = self::$percentileCache[$role][$dbMetricKey] ?? [];
        if (empty($thresholds)) return 50.0;
        $prevP = 0.0; $prevV = 0.0;
        foreach ($thresholds as $t) {
            $currP = $t['p']; $currV = $t['v'];
            if ($value <= $currV) {
                if ($currV == $prevV) return $currP;
                $fraction = ($value - $prevV) / ($currV - $prevV);
                return $prevP + ($fraction * ($currP - $prevP));
            }
            $prevP = $currP; $prevV = $currV;
        }
        return 99.0;
    }
    
    private function mapMetricKeys(string $configKey): array {
        $universalMap = config('fbref_stats_mapping.universal_to_column', []);
        $shortToUniversal = [
            'gk_save_pct' => 'stats_keeper_gk_save_pct',
            'gk_saves' => 'stats_keeper_gk_saves',
            'gk_clean_sheets' => 'stats_keeper_gk_clean_sheets_pct',
            'gk_goals_against_per90' => 'stats_keeper_gk_goals_against_per90',
            'gk_penalties_saved' => 'stats_keeper_gk_pens_saved',
            'passes_pct' => 'stats_passing_passes_pct',
            'errors' => 'stats_defense_errors',
            'assists' => 'stats_gca_sca',
            'defense_tackles_won' => 'stats_defense_tackles_won',
            'defense_blocks_general' => 'stats_defense_blocks',
            'defense_interceptions' => 'stats_defense_interceptions',
            'aerials_won' => 'stats_misc_aerials_won',
            'goals_assists' => 'stats_goals_assists',
            'expected_goals' => 'stats_expected_goals',
            'sca' => 'stats_gca_sca',
            'passes_progressive_distance' => 'stats_passing_passes_progressive_distance',
            'passes_into_final_third' => 'stats_passing_passes_into_final_third',
            'passes_pct_long' => 'stats_passing_passes_pct_long',
            'touches_def_pen_area' => 'stats_possession_touches_def_pen_area',
        ];
        $dbKey = $shortToUniversal[$configKey] ?? $configKey;
        $statKey = $universalMap[$dbKey] ?? $configKey;
        if ($dbKey === 'stats_keeper_gk_goals_against_per90') $statKey = 'goals_conceded';
        if ($dbKey === 'stats_keeper_gk_clean_sheets_pct') $statKey = 'clean_sheet_pct';
        return ['db' => $dbKey, 'stat' => $statKey];
    }
    
    private function applyTierOverlap(float $rawBonus, int $teamTier, array $tuning, string $archName): float {
        if ($teamTier >= 3 || $rawBonus <= 0) return $rawBonus;
        if (!in_array($archName, ['muro', 'colosso'])) return $rawBonus;
        $overlapFactor = $tuning['tier_archetype_overlap'] ?? 0.0;
        if ($overlapFactor <= 0) return $rawBonus;
        $tierReduction = ($teamTier === 1) ? 1.0 : 0.5;
        $discount = $rawBonus * $overlapFactor * $tierReduction;
        return $rawBonus - $discount;
    }
    
    private function applyModulationRules(array $activeArchetypes, array $config, int $currentTier): float {
        $synergyBonus = 0.0;
        $rules = $config['modulation_rules']['synergy'] ?? [];
        foreach ($rules as $rule) {
            $conditions = $rule['conditions'] ?? [];
            $applies = true;
            if (isset($conditions['archetypes']) && array_diff($conditions['archetypes'], $activeArchetypes)) $applies = false;
            if ($applies && isset($conditions['min_tier']) && $currentTier < $conditions['min_tier']) $applies = false;
            if ($applies && isset($conditions['max_tier']) && $currentTier > $conditions['max_tier']) $applies = false;
            if ($applies) $synergyBonus += ($rule['factor'] ?? 0.0);
        }
        return $synergyBonus;
    }
    
    private function enrichWithFbrefData(Collection $h, int $pid) {
        $s=$h->pluck('season_year')->toArray();
        $f=PlayerFbrefStat::where('player_id',$pid)->whereIn('season_year',$s)->get()->keyBy('season_year');
        foreach($h as $st){
            $y=(string)$st->season_year; $d=$f->get($y);
            $st->clean_sheet=$d->gk_clean_sheets??0; $st->clean_sheet_pct=$d->gk_cs_percentage??0;
            $st->gk_save_percentage=$d->gk_save_percentage??0; $st->gk_saves=$d->gk_saves??0;
            $st->gk_penalties_saved=$d->gk_penalties_saved??0; $st->errors=$d->errors??0;
            $st->passes_pct=$d->passes_pct??0;
            $st->defense_tackles_won=$d->defense_tackles_won??0; $st->defense_interceptions=$d->defense_interceptions??0;
            $st->defense_blocks_general=$d->defense_blocks_general??0; $st->aerials_won=$d->aerials_won??0;
            $st->xg = $d->xg ?? 0; $st->xg_assist = $d->xg_assist ?? 0;
            $st->sca = $d->sca ?? 0; $st->goals_assists = $d->goals_assists ?? 0;
            $st->passes_progressive_distance = $d->passes_progressive_distance ?? 0;
            $st->passes_into_final_third = $d->passes_into_final_third ?? 0;
            $st->passes_pct_long = $d->passes_pct_long ?? 0; $st->touches_def_pen_area = $d->touches_def_pen_area ?? 0;
        }
        return $h;
    }
    
    private function calculateSeasonWeights(array $years): array { rsort($years); $weights = []; $decay = config('projection_settings.season_decay_factor', 0.90); foreach($years as $index => $year) { $weights[$year] = pow($decay, $index); } return $weights; }
    
    private function calculateWeightedAverages(Collection $v, array $w, array $roleConfig): array {
        $map=['mv'=>'avg_rating','fanta_mv'=>'fanta_avg_rating','gol_fatti'=>'goals_scored','assist'=>'assists',
            'gol_subiti'=>'goals_conceded','clean_sheet'=>'clean_sheet','clean_sheet_pct'=>'clean_sheet_pct',
            'gk_save_percentage'=>'gk_save_percentage','gk_saves'=>'gk_saves','errors'=>'errors', 'passes_pct'=>'passes_pct',
            'defense_tackles_won'=>'defense_tackles_won', 'defense_interceptions'=>'defense_interceptions',
            'defense_blocks_general'=>'defense_blocks_general', 'aerials_won'=>'aerials_won',
            'expected_goals'=>'xg', 'xg_assist'=>'xg_assist', 'sca'=>'sca', 'goals_assists'=>'goals_assists',
            'passes_progressive_distance'=>'passes_progressive_distance', 'passes_into_final_third'=>'passes_into_final_third',
            'passes_pct_long'=>'passes_pct_long', 'touches_def_pen_area'=>'touches_def_pen_area'];
        $res=[]; $tot=array_sum($w); if(!$tot)return[]; $weightedGames = 0; foreach ($v as $s) { $weightedGames += $s->games_played * $w[$s->season_year]; } $avgGames = $weightedGames / $tot; $reliability = ($avgGames > 10) ? 1.0 : max(0.1, $avgGames / 25.0);
        $baseReg = $roleConfig['engine_parameters']['base_rating'] ?? 6.00;
        foreach($map as $k=>$c){ $s=0; foreach($v as $i) { $val = (float)($i->$c??0); if (!in_array($k, ['mv','fanta_mv','gk_save_percentage','clean_sheet_pct','passes_pct', 'passes_pct_long'])) $val /= ($i->games_played?:1); $s += $val * $w[$i->season_year]; } $val = $s/$tot; if ($k === 'mv' && $reliability < 1.0) $val = ($val * $reliability) + ($baseReg * (1.0 - $reliability)); $res[$k] = $val; } return $res; }
        
        private function calculateAgeModifier(Player $p, ?int $t): float { $cy = (int)date('Y'); $pa = $p->age; if ($t && $pa) $pa -= ($cy - $t); if (!$pa) return 1.0; if ($pa < 28) return 1.0 + ((28 - $pa) * 0.01); if ($pa > 38) return 1.0 - ((38 - $pa) * 0.015); return 1.0; }
        private function applyModulations(array $s, float $a, array $t): array { $modulatedStats = $s; foreach (['mv', 'gk_saves', 'clean_sheet'] as $f) if (isset($modulatedStats[$f])) $modulatedStats[$f] *= $a; foreach (['gol_subiti', 'goals_conceded', 'gk_goals_against_per90'] as $f) if (isset($modulatedStats[$f])) $modulatedStats[$f] *= $t['defensive']; return $modulatedStats; }
        private function estimateGamesPlayed(Player $p, Collection $v, float $a): int { if ($v->isEmpty()) return config('projection_settings.fallback_gp_if_no_history', 0); $avgGP = $v->avg('games_played'); $est = $avgGP * $a; $tier = $p->team->tier ?? 3; $est *= config("projection_settings.team_tier_presence_factor.{$tier}", 1.0); return (int)round(max(config('projection_settings.min_projected_presences', 5), min(config('projection_settings.max_projected_presences', 38), $est))); }
        private function applyRegressionToMean(array $stats, string $role): array { foreach ($stats as $key => $value) { if ((str_contains($key, 'pct') || str_contains($key, 'percentage')) && $value > 100.0) $stats[$key] = 100.0; if (($key === 'mv' || $key === 'avg_rating') && $value > 10.0) $stats[$key] = 6.0; } $config = config('projection_settings.regression_means'); if (!$config) return $stats; $defaultFactor = $config['regression_factor'] ?? 0.1; $metricFactors = $config['metric_factors'] ?? []; $roleMeans = $config['means_by_role'][$role] ?? []; $defaultMeans = $config['default_means'] ?? []; $referenceMeans = array_merge($defaultMeans, $roleMeans); foreach ($stats as $key => $value) { if (isset($referenceMeans[$key])) { $targetMean = $referenceMeans[$key]; $factor = $metricFactors[$key] ?? $defaultFactor; $stats[$key] = ($stats[$key] * (1.0 - $factor)) + ($targetMean * $factor); } } return $stats; }
}