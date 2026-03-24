<?php

namespace App\Services;

use App\Traits\ParsesFbrefHtml;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Importazione necessaria per risolvere l'errore

class TeamDataService
{
    use ParsesFbrefHtml;
    
    public function scrapeFromFbrefUrl(string $url, int $year, string $division)
    {
        $logPath = storage_path('logs/Teams/TeamHistoricalStanding.log');
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        $logger->info("--- 🚀 AVVIO SINCRONIZZAZIONE AVANZATA ---");
        
        // 1. Definiamo i Target (Solo le squadre con il flag serie_a_team)
        $targetTeams = DB::table('teams')->where('serie_a_team', 1)->get();
        Log::info("Scraper: Inizio elaborazione per " . $targetTeams->count() . " team target.");
        
        // 2. Download via Proxy
        $response = Http::timeout(120)->get("http://api.scraperapi.com?api_key=".env('SCRAPER_API_KEY')."&url=".urlencode($url));
        if ($response->failed()) throw new \Exception("Errore Proxy ScraperAPI");
        
        // 3. Parsing Universale (Scommento e Mappatura data-stat)
        $allTables = $this->parseEntirePage($response->body());
        $tableId = collect(array_keys($allTables))->first(fn($id) => str_contains($id, 'overall'));
        $scrapedData = $allTables[$tableId] ?? [];
        
        // Mappa Colonne DB
        $map = [
            'rank' => 'position', 'games' => 'played_games', 'wins' => 'won',
            'ties' => 'draw', 'losses' => 'lost', 'points' => 'points',
            'goals_for' => 'goals_for', 'goals_against' => 'goals_against',
            'goal_diff' => 'goal_difference'
        ];
        
        $upsertData = []; // Accumulatore per le righe da inserire
        
        // 4. Algoritmo di Matching (ID -> Short Name Slug -> Full Name Slug -> Fuzzy)
        foreach ($targetTeams as $team) {
            $logger->info("🧐 Analisi Target: {$team->name} (DB ID: {$team->id})");
            
            $foundRow = null;
            $matchReason = "";
            
            foreach ($scrapedData as $fbrefName => $values) {
                $slugFbref = Str::slug($fbrefName);
                $slugDbName = Str::slug($team->name);
                $slugDbShort = Str::slug($team->short_name ?? '');
                
                // LIVELLO 1: Match per FBref ID (Infallibile)
                if (!empty($team->fbref_id) && isset($values['fbref_id']) && $team->fbref_id === $values['fbref_id']) {
                    $foundRow = $values;
                    $matchReason = "ID Univoco";
                    break;
                }
                
                // LIVELLO 2: Match per Slug (Short Name o Full Name)
                if ($slugFbref === $slugDbShort || $slugFbref === $slugDbName) {
                    $foundRow = $values;
                    $matchReason = "Slug Match";
                    break;
                }
                
                // LIVELLO 3: Fuzzy Match (Contenuto stringa)
                if (str_contains($slugDbName, $slugFbref) || str_contains($slugFbref, $slugDbName)) {
                    $foundRow = $values;
                    $matchReason = "Fuzzy Match (Similitudine)";
                    break;
                }
            }
            
            if ($foundRow) {
                $saveData = [
                    'team_id' => $team->id,
                    'season_year' => $year,
                    'league_name' => ($division === 'A') ? 'Serie A' : 'Serie B',
                    'data_source' => 'SCRAPER_V2_ADVANCED',
                    'created_at' => now(), // Fisso: Valorizzazione iniziale per nuovi insert
                    'updated_at' => now(),
                ];
                
                foreach ($map as $fbrefKey => $dbCol) {
                    $val = $foundRow[$fbrefKey] ?? 0;
                    $saveData[$dbCol] = (int)str_replace(',', '', $val);
                }
                
                $upsertData[] = $saveData;
                
                Log::info("✅ Match trovato: {$team->name}");
                $logger->info("✔️ Successo: Match trovato via [$matchReason]. Preparato per salvataggio.");
            } else {
                Log::warning("⚠️ Mancante: {$team->name}");
                $logger->warning("❌ Fallimento: Impossibile trovare dati per '{$team->name}' su FBref.");
            }
        }
        
        // Operazione database: Bulk Upsert! (Unico salvataggio invece di N query)
        if (!empty($upsertData)) {
            DB::table('team_historical_standings')->upsert(
                $upsertData,
                ['team_id', 'season_year'],
                ['league_name', 'data_source', 'updated_at', 'position', 'played_games', 'won', 'draw', 'lost', 'points', 'goals_for', 'goals_against', 'goal_difference']
            );
            $logger->info("✔️ Operazione completata: Inserite o aggiornate " . count($upsertData) . " righe in massa.");
            
            DB::table('import_logs')->insert([
                'original_file_name' => substr($url, 0, 250),
                'import_type' => 'team_standing_batch',
                'status' => 'success',
                'details' => json_encode(['season_year' => $year, 'league_name' => ($division === 'A') ? 'Serie A' : 'Serie B']),
                'rows_processed' => $targetTeams->count(),
                'rows_updated' => count($upsertData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    public function getCoverageData(array $seasons)
    {
        $teams = DB::table('teams')->where('serie_a_team', 1)->orderBy('name')->get();
        $matrix = [];
        foreach ($teams as $team) {
            $row = ['team_name' => $team->name];
            foreach ($seasons as $season) {
                $row[$season] = DB::table('team_historical_standings')
                ->where('team_id', $team->id)
                ->where('season_year', $season)
                ->exists();
            }
            $matrix[] = $row;
        }
        return $matrix;
    }

    public function syncAllMissingCoverage(array $seasons)
    {
        foreach ($seasons as $year) {
            $nextYear = $year + 1;
            // Serie A
            $urlA = "https://fbref.com/en/comps/11/{$year}-{$nextYear}/{$year}-{$nextYear}-Serie-A-Stats";
            $this->scrapeFromFbrefUrl($urlA, $year, 'A');
            
            // Serie B
            $urlB = "https://fbref.com/en/comps/18/{$year}-{$nextYear}/{$year}-{$nextYear}-Serie-B-Stats";
            $this->scrapeFromFbrefUrl($urlB, $year, 'B');
        }
    }

    /**
     * Calcola e aggiorna il Tier (1-5) per ogni squadra usando la Gold Standard Config.
     *
     * Logica:
     *  - Score = (1 - pts/(played_games×3)) × 20  [Points Mode, più preciso della posizione]
     *  - Serie B: score moltiplicato per (CF × divisore/10) invece del offset +10 additivo
     *  - Divisore fisso = 17: le stagioni mancanti contano come "0" nel denominatore,
     *    penalizzando le squadre senza storia completa invece di premiarle.
     *  - Pesi decrescenti accelerati [7,4,2,1,1] per 5 stagioni.
     *  - Modulatori post-score: T4-5 × 1.10x, T1-2 × 1.00x.
     *
     * Calibrazione: MAE=1.18, Affinity |Δ|≤3 = 94.1% (TierEvolutionTest, 2026-03-24)
     *
     * @param int $lookbackYears Numero di stagioni passate da analizzare (default: 5)
     * @return array ['updated' => int, 'skipped' => int]
     */
    public function updateTeamTiers(int $lookbackYears = 5): array
    {
        $logPath = storage_path('logs/Tiers/TeamsUpdateTiers.log');
        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);

        // ── Carica parametri dalla config (con fallback ai valori Gold Standard) ──
        $cfg          = config('projection_settings.tier_calculation', []);
        $usePointsMode = $cfg['use_points_mode']            ?? true;
        $cf            = (float) ($cfg['serie_b_conversion_factor'] ?? 0.95);
        $fixedDivisor  = (int)   ($cfg['fixed_divisor']             ?? 17);
        $modOffensive  = (float) ($cfg['mod_tier_offensive']        ?? 1.00);
        $modDefensive  = (float) ($cfg['mod_tier_defensive']        ?? 1.10);
        $thresholds    = $cfg['tier_thresholds'] ?? [1 => 5.5, 2 => 9.5, 3 => 13.5, 4 => 17.5];

        // ── Stagioni da analizzare ─────────────────────────────────────────────
        // Esclude la stagione in corso: se siamo prima di agosto usa Y-2, altrimenti Y-1
        $currentYear = (date('n') < 8) ? (int) date('Y') - 2 : (int) date('Y') - 1;
        $seasons     = [];
        for ($i = 0; $i < $lookbackYears; $i++) {
            $seasons[] = $currentYear - $i;
        }

        // ── Pesi temporali ────────────────────────────────────────────────────
        if ($lookbackYears === 5) {
            $weights = [7, 4, 2, 1, 1]; // scala accelerata ottimizzata
        } else {
            $weights = array_reverse(range(1, $lookbackYears));
        }

        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("🏆  GOLD STANDARD TIER CALCULATION");
        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("⚙️  Lookback    : {$lookbackYears} stagioni → " . implode(', ', $seasons));
        $logger->info("⚙️  Pesi        : " . implode(', ', $weights) . " | Divisore fisso: {$fixedDivisor}");
        $logger->info("⚙️  Modalità    : " . ($usePointsMode ? 'PUNTI NORMALIZZATI ✅' : 'POSIZIONE (legacy)'));
        $logger->info("⚙️  CF Serie B  : {$cf} | ModT1-2: {$modOffensive}x | ModT4-5: {$modDefensive}x");
        $logger->info(str_repeat('─', 70));

        $teams      = DB::table('teams')->where('serie_a_team', 1)->get();
        $updated    = 0;
        $skipped    = 0;
        $upsertData = [];

        foreach ($teams as $team) {
            $weightedScoreSum = 0.0;
            $seasonDetails    = [];

            foreach ($seasons as $idx => $season) {
                $standing = DB::table('team_historical_standings')
                    ->where('team_id', $team->id)
                    ->where('season_year', $season)
                    ->first();

                if (!$standing || $standing->position <= 0) {
                    $seasonDetails[] = "{$season}→n/d";
                    // Stagione mancante: contribuisce 0 al numeratore ma il denominatore
                    // resta fisso a $fixedDivisor → penalizza correttamente
                    continue;
                }

                $w        = $weights[$idx];
                $isSerieA = ($standing->league_name === 'Serie A');
                $leagueLabel = $isSerieA ? 'A' : 'B';

                // ── Calcolo score base ─────────────────────────────────────────
                $hasPtsData   = $usePointsMode
                    && $standing->points > 0
                    && $standing->played_games > 0;

                if ($hasPtsData) {
                    // POINTS MODE: normalizza i punti su scala 0-20
                    // 0 = squadra perfetta (tutti i punti), 20 = squadra nulla (0 punti)
                    $ptsRatio  = min(1.0, $standing->points / ($standing->played_games * 3));
                    $baseScore = (1.0 - $ptsRatio) * 20.0;
                    $modeLabel = "pts({$standing->points}/{$standing->played_games}×3)";
                } else {
                    // FALLBACK: posizione raw (legacy)
                    $baseScore = (float) $standing->position;
                    $modeLabel = "pos";
                }

                // ── Conversione Serie B ────────────────────────────────────────
                // Moltiplicatore CF×(div/10) sostituisce definitivamente il +10 additivo.
                // CF=0.95, div=17 → moltiplicatore = 1.615
                // Questo scala il range 0-20 di Serie B a 0-32.3, riflettendo la distanza
                // dal livello Serie A senza andare a regime su valori assoluti.
                if (!$isSerieA) {
                    $serieBMultiplier = $cf * ($fixedDivisor / 10.0);
                    $baseScore       *= $serieBMultiplier;
                    $modeLabel       .= "×CF({$serieBMultiplier})";
                }

                $contribution      = $baseScore * $w;
                $weightedScoreSum += $contribution;

                $seasonDetails[] = sprintf(
                    "%d→%s[%s]→score%.2f×peso%d=%.2f",
                    $season, ($hasPtsData ? "pts" : "pos{$standing->position}"),
                    $leagueLabel, $baseScore, $w, $contribution
                );
            }

            // ── Posizione media con divisore fisso ────────────────────────────
            // Il divisore è sempre $fixedDivisor (17), non la somma dei pesi effettivi.
            // Questo penalizza le squadre con stagioni mancanti.
            $avgPosition = round($weightedScoreSum / $fixedDivisor, 4);

            if ($avgPosition <= 0) {
                $logger->warning("⚠️  {$team->name}: dati insufficienti (avg=0). Tier invariato.");
                $skipped++;
                continue;
            }

            // ── Modulatori pre-tier ────────────────────────────────────────────
            // Assegna il tier grezzo per decidere quale modulatore applicare
            $tierRaw = $this->assignTierByThresholds($avgPosition, $thresholds);

            if ($tierRaw <= 2 && $modOffensive != 1.00) {
                $avgPositionMod = round($avgPosition / $modOffensive, 4);
                $modNote = "÷{$modOffensive} (T1-2 off)";
            } elseif ($tierRaw >= 4) {
                $avgPositionMod = round($avgPosition * $modDefensive, 4);
                $modNote = "×{$modDefensive} (T4-5 def)";
            } else {
                $avgPositionMod = $avgPosition;
                $modNote = "×1.00 (T3 neutro)";
            }

            // Tier finale con posizione modulata
            $tier = $this->assignTierByThresholds($avgPositionMod, $thresholds);

            $logger->info("📌 {$team->name}");
            $logger->info("   Stagioni   : " . implode(' | ', $seasonDetails));
            $logger->info(sprintf(
                "   Somma pesata: %.4f / divisore fisso: %d = avg: %.4f | mod: %s → avg_mod: %.4f",
                $weightedScoreSum, $fixedDivisor, $avgPosition, $modNote, $avgPositionMod
            ));
            $logger->info("   → Tier assegnato: {$tier}");

            $upsertData[] = [
                'id'             => $team->id,
                'tier'           => $tier,
                'posizione_media' => $avgPositionMod,
            ];
            $updated++;
        }

        // ── Aggiornamento batch ────────────────────────────────────────────────
        foreach ($upsertData as $row) {
            DB::table('teams')->where('id', $row['id'])->update([
                'tier'            => $row['tier'],
                'posizione_media' => $row['posizione_media'],
            ]);
        }

        DB::table('import_logs')->insert([
            'original_file_name' => 'teams:update-tiers',
            'import_type'        => 'team_tier_update',
            'status'             => 'success',
            'details'            => json_encode([
                'engine'                    => 'gold_standard_v2',
                'lookback_years'            => $lookbackYears,
                'seasons'                   => $seasons,
                'use_points_mode'           => $usePointsMode,
                'serie_b_conversion_factor' => $cf,
                'fixed_divisor'             => $fixedDivisor,
                'calibration'               => 'MAE=1.18 Affinity=94.1% (TierEvolutionTest 2026-03-24)',
            ]),
            'rows_processed'     => $teams->count(),
            'rows_updated'       => $updated,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $logger->info(str_repeat('─', 70));
        $logger->info("--- ✅ GOLD STANDARD TIER COMPLETATO: {$updated} aggiornati, {$skipped} saltati ---");

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Assegna il tier in base alle soglie configurabili.
     * Le soglie sono in ordine crescente: tier più basso = squadra migliore.
     */
    private function assignTierByThresholds(float $score, array $thresholds): int
    {
        foreach ($thresholds as $tier => $maxScore) {
            if ($score <= $maxScore) {
                return (int) $tier;
            }
        }
        return 5;
    }
}