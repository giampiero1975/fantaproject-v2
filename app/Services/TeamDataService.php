<?php

namespace App\Services;

use App\Traits\ParsesFbrefHtml;
use App\Traits\FindsTeam;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\League;
use App\Models\Team;

class TeamDataService
{
    use ParsesFbrefHtml, FindsTeam;
    
    public function scrapeFromFbrefUrl(string $url, int $year, string $division)
    {
        $logPath = storage_path('logs/Teams/TeamHistoricalStanding.log');
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        $logger->info("--- 🚀 AVVIO SINCRONIZZAZIONE AVANZATA ---");
        
        // RECUPERO LEAGUE DINAMICO (Risanamento Step 2)
        // Se division è 'A' o 'B', mappiamo ai nomi a DB. Se è già il nome campionato, meglio ancora.
        $leagueName = ($division === 'A') ? 'Serie A' : (($division === 'B') ? 'Serie B' : $division);
        $league = \App\Models\League::where('name', $leagueName)->first();
        
        if (!$league || empty($league->fbref_id)) {
            $logger->error("Errore: Lega '{$leagueName}' non trovata o manca fbref_id a DB.");
            throw new \Exception("Lega '{$leagueName}' non configurata correttamente per lo scraping.");
        }

        // 1. Definiamo i Target (Squadre attive nella stagione/competizione indicata)
        $currentSeasonModel = \App\Models\Season::where('season_year', $year)->first();
        $seasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;
        
        $targetTeams = \App\Models\Team::whereHas('teamSeasons', function($q) use ($seasonId) {
            $q->where('season_id', $seasonId)->where('is_active', true);
        })->get();
        
        Log::info("Scraper: Inizio elaborazione per " . $targetTeams->count() . " team target.");
        
        // 2. Download via Proxy (Dinamico e Resiliente)
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();
        if (!$proxy) throw new \Exception("Nessun proxy disponibile per lo scraping.");

        $proxyUrl = $proxyManager->getProxyUrl($proxy, $url);
        
        $startTime = microtime(true);
        $response = Http::timeout(40)->withoutVerifying()->get($proxyUrl);
        
        if ($response->failed()) {
             $proxyManager->markAsUnreliable($proxy, "Status: " . $response->status());
             throw new \Exception("Errore Proxy {$proxy->name}: " . $response->status());
        }
        
        $proxyManager->syncBalance($proxy); // Aggiorna i crediti subito
        
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
        
        foreach ($targetTeams as $team) {
            $logger->info("🧐 Analisi Target: {$team->name} (DB ID: {$team->id})");
            
            $foundRow = null;
            $matchReason = "";
            
            foreach ($scrapedData as $fbrefName => $values) {
                $slugFbref = Str::slug($fbrefName);
                $slugDbName = Str::slug($team->name);
                $slugDbShort = Str::slug($team->short_name ?? '');
                
                if (!empty($team->fbref_id) && isset($values['fbref_id']) && $team->fbref_id === $values['fbref_id']) {
                    $foundRow = $values;
                    $matchReason = "ID Univoco";
                    break;
                }
                
                if ($slugFbref === $slugDbShort || $slugFbref === $slugDbName) {
                    $foundRow = $values;
                    $matchReason = "Slug Match";
                    break;
                }
                
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
                    'league_name' => $league->name,
                    'data_source' => 'SCRAPER_V2_ADVANCED',
                    'updated_at' => now(),
                ];
                
                foreach ($map as $fbrefKey => $dbCol) {
                    $val = $foundRow[$fbrefKey] ?? 0;
                    $saveData[$dbCol] = (int)str_replace(',', '', $val);
                }
                
                $upsertData[] = $saveData;
                $logger->info("✔️ Successo: Match trovato via [$matchReason].");
            } else {
                $logger->warning("❌ Fallimento: Impossibile trovare dati per '{$team->name}' su FBref.");
            }
        }
        
        if (!empty($upsertData)) {
            DB::table('team_historical_standings')->upsert(
                $upsertData,
                ['team_id', 'season_year'],
                ['league_name', 'data_source', 'updated_at', 'position', 'played_games', 'won', 'draw', 'lost', 'points', 'goals_for', 'goals_against', 'goal_difference']
            );
            
            DB::table('import_logs')->insert([
                'original_file_name' => substr($url, 0, 250),
                'import_type' => 'team_standing_batch',
                'status' => 'success',
                'details' => json_encode(['season_year' => $year, 'league_name' => $league->name]),
                'rows_processed' => $targetTeams->count(),
                'rows_updated' => count($upsertData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    public function getCoverageData(array $seasons)
    {
        // Recuperiamo gli ID di tutte le squadre che hanno almeno un dato storico nei 4 anni target
        // O che sono attive nella stagione corrente (ID 1)
        $currentSeasonModel = \App\Models\Season::where('is_current', true)->first();
        $currentSeasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        $teamIdsFromStandings = DB::table('team_historical_standings')
            ->whereIn('season_year', $seasons)
            ->pluck('team_id')
            ->unique();

        $activeTeamIds = \App\Models\Team::whereHas('teamSeasons', function($q) use ($currentSeasonId) {
            $q->where('season_id', $currentSeasonId)->where('is_active', true);
        })->pluck('id');

        $allTargetTeamIds = $teamIdsFromStandings->merge($activeTeamIds)->unique();

        $teams = \App\Models\Team::whereIn('id', $allTargetTeamIds)
            ->orderBy('name')
            ->get();

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
    public function updateTeamTiers(?int $lookbackYears = null): array
    {
        $logPath = storage_path('logs/Tiers/TeamsUpdateTiers.log');
        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);

        // ── Carica parametri dalla config (Modello Fattore Potenza) ──
        $cfg           = config('projection_settings.tiers', []);
        $fixedDivisor  = (int)   ($cfg['fixed_divisor']             ?? 20);
        
        if (!$lookbackYears) {
            $lookbackYears = (int) ($cfg['lookback_seasons'] ?? 4);
        }
        
        $thresholdsRaw = $cfg['thresholds'] ?? [
            't1' => 7.5,
            't2' => 9.5,
            't3' => 12.5,
            't4' => 13.5,
        ];
        
        // Normalizziamo le soglie per la funzione di assegnazione
        $thresholds = [
            1 => $thresholdsRaw['t1'],
            2 => $thresholdsRaw['t2'],
            3 => $thresholdsRaw['t3'],
            4 => $thresholdsRaw['t4'],
        ];

        // Esclude la stagione in corso
        $currentYear = \App\Helpers\SeasonHelper::getCurrentSeason();
        $lastConcluded = $currentYear - 1;
        $seasons     = [];
        for ($i = 0; $i < $lookbackYears; $i++) {
            $seasons[] = $lastConcluded - $i;
        }

        // 2. Pesi Dinamici (set Gold Standard ritagliato)
        $baseWeights = $cfg['season_decay_weights'] ?? [7, 4, 2, 1];
        
        // Se il lookback richiesto è più lungo dei pesi definiti, estendiamo con pesi = 1
        $weights = array_slice($baseWeights, 0, $lookbackYears);
        if ($lookbackYears > count($baseWeights)) {
            $diff = $lookbackYears - count($baseWeights);
            $weights = array_merge($weights, array_fill(0, $diff, 1));
        }

        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("🏆  POWER FACTOR CALCULATION (GOLD STANDARD)");
        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("⚙️  Lookback    : {$lookbackYears} stagioni → " . implode(', ', $seasons));
        $logger->info("⚙️  Pesi usati  : " . implode(', ', $weights) . " | Divisore Fisso: {$fixedDivisor}");
        $logger->info("⚙️  Motore      : Cinetico (PTS 60%, GF 28%, GS 12%) ✅");
        $logger->info("⚙️  Soglie      : T1:{$thresholds[1]} T2:{$thresholds[2]} T3:{$thresholds[3]} T4:{$thresholds[4]}");
        $logger->info(str_repeat('─', 70));

        $teams      = \App\Models\Team::all(); // Calcoliamo il tier globale per tutte le squadre master
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

                $w = $weights[$idx];

                if (!$standing) {
                    // Record realmente assente (Serie C o fallimento) -> Penale d'ufficio
                    $baseScore = 20.0;
                    $cf_pts = config('projection_settings.tiers.serie_b_coefficients.points', 0.76);
                    $baseScore *= ($cf_pts * 1.5); // Penale aggravata (1.5x) per chi è "fuori dai radar"

                    $contribution = $baseScore * $w;
                    $weightedScoreSum += $contribution;
                    
                    $seasonDetails[] = sprintf("%d→MANCANTE[score%.2f×peso%d=%.2f]", $season, $baseScore, $w, $contribution);
                    continue;
                }

                $isSerieA = (isset($standing->league_name) && $standing->league_name === 'Serie A');
                $leagueLabel = $isSerieA ? 'A' : 'B';

                // --- 1. RECUPERO DATI ---
                $rawPts = $standing->points ?? 0;
                $rawGf  = $standing->goals_for ?? 0;
                $rawGs  = $standing->goals_against ?? 0;
                $played = $standing->played_games ?? $standing->played ?? 0;
                $maxPossiblePts = $played > 0 ? $played * 3 : 114;

                $cf_pts = config('projection_settings.tiers.serie_b_coefficients.points', 0.70);
                $cf_gf  = config('projection_settings.tiers.serie_b_coefficients.goals_for', 0.60);
                $cf_gs  = config('projection_settings.tiers.serie_b_coefficients.goals_against', 0.90);

                // --- 2. NORMALIZZAZIONE (Scala 0-20) ---
                $ptsComp = (1.0 - (min(1.0, $rawPts / $maxPossiblePts))) * 20.0;
                $gfComp  = (1.0 - (min(1.0, $rawGf  / 90.0))) * 20.0;
                $gsComp  = (min(1.0, $rawGs  / 75.0)) * 20.0;

                // --- 2b. CORREZIONE SERIE B (MALUS VERO: DIVISIONE PER PEGGIORARE) ---
                if (!$isSerieA) {
                    $ptsComp = $ptsComp / $cf_pts;
                    $gfComp  = $gfComp  / $cf_gf;
                    $gsComp  = $gsComp  / $cf_gs;
                }

                // --- 3. PESATURA POTENZA (60/28/12) ---
                $w_pts = config('projection_settings.tiers.weights.points', 0.60);
                $w_gf  = config('projection_settings.tiers.weights.goals_for', 0.28);
                $w_gs  = config('projection_settings.tiers.weights.goals_against', 0.12);

                $baseScore = ($ptsComp * $w_pts) + ($gfComp * $w_gf) + ($gsComp * $w_gs);

                $contribution = $baseScore * $w;
                $weightedScoreSum += $contribution;

                $seasonDetails[] = sprintf(
                    "%d→%s[PtsComp%.1f,GFComp%.1f,GSComp%.1f]→score%.2f×peso%d=%.2f",
                    $season, $leagueLabel, $ptsComp, $gfComp, $gsComp, $baseScore, $w, $contribution
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

            // ── FEATURE: Trend Penalty (Decadenza) ────────────────────────────
            // Se le ultime 3 stagioni in Serie A mostrano un peggioramento costante,
            // applichiamo il malus definito in config.
            $trendMalus = 1.00;
            $posHistory = [];
            foreach ($seasons as $s) {
                $st = DB::table('team_historical_standings')
                    ->where('team_id', $team->id)
                    ->where('season_year', $s)
                    ->where('league_name', 'Serie A')
                    ->first();
                if ($st && $st->position > 0) {
                    $posHistory[] = $st->position;
                }
            }

            $trendPenaltyCfg = (float) ($cfg['trend_penalty'] ?? 1.05);
            if ($trendPenaltyCfg > 1.00 && count($posHistory) >= 3) {
                // posHistory[0] è la più recente (es. 2024), posHistory[1] la precedente (2023)...
                if ($posHistory[0] > $posHistory[1] && $posHistory[1] > $posHistory[2]) {
                    $trendMalus = $trendPenaltyCfg;
                    $avgPosition = round($avgPosition * $trendMalus, 4);
                }
            }

            // ── Modulatori pre-tier ────────────────────────────────────────────
            // Assegna il tier grezzo per decidere quale modulatore applicare
            $tierRaw = $this->assignTierByThresholds($avgPosition, $thresholds);

            // ── Posizione modulata (No Modulatori nel Gold Standard v3) ───────
            $avgPositionMod = $avgPosition;
            $modNote = "×1.00 (Standard)";

            // Se è stato applicato un malus di trend, lo segnaliamo nel log
            if ($trendMalus > 1.00) {
                $modNote .= " + TREND MALUS {$trendMalus}x ⚠️";
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
                'id'                      => $team->id,
                'tier_globale'            => $tier,
                'posizione_media_storica' => $avgPositionMod,
            ];
            $updated++;
        }

        // ── Aggiornamento batch ────────────────────────────────────────────────
        foreach ($upsertData as $row) {
            DB::table('teams')->where('id', $row['id'])->update([
                'tier_globale'            => $row['tier_globale'],
                'posizione_media_storica' => $row['posizione_media_storica'],
            ]);
        }

        DB::table('import_logs')->insert([
            'original_file_name' => 'teams:update-tiers',
            'import_type'        => 'team_tier_update',
            'status'             => 'success',
            'details'            => json_encode([
                'engine'                    => 'power_factor_v1',
                'lookback_years'            => $lookbackYears,
                'seasons'                   => $seasons,
                'weights'                   => config('projection_settings.tiers.weights'),
                'fixed_divisor'             => $fixedDivisor,
                'calibration'               => 'MAE=1.05 Affinity=95.0% (GoldStandard-GridSearch 2026-04-08)',
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

    // ─── API football-data.org ───────────────────────────────────────────────

    /**
     * Recupera la rosa di una squadra dall'API football-data.org.
     * Endpoint: GET /v4/teams/{id}  →  field: squad[]
     *
     * @param  int $apiTeamId  Il valore di teams.api_id
     * @return array           Array di giocatori: [id, name, position, dateOfBirth, ...]
     */
    public function getSquad(int $apiTeamId): array
    {
        $apiKey = config('services.player_stats_api.providers.football_data_org.api_key');

        if (empty($apiKey)) {
            \Illuminate\Support\Facades\Log::error('TeamDataService::getSquad — FOOTBALL_DATA_API_KEY non configurata in .env');
            return [];
        }

        $url = "https://api.football-data.org/v4/teams/{$apiTeamId}";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(15)->get($url);

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::warning(
                    "TeamDataService::getSquad — HTTP {$response->status()} per teamId={$apiTeamId}"
                );
                return [];
            }

            $data = $response->json();
            return $data['squad'] ?? [];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                "TeamDataService::getSquad — Exception per teamId={$apiTeamId}: " . $e->getMessage()
            );
            return [];
        }
    }

    // ─── Importazione Anagrafica Squadre ────────────────────────────────────────

    /**
     * Importa (o aggiorna) le squadre di Serie A da football-data.org.
     *
     * Endpoint: GET /v4/competitions/SA/teams?season={season_year}
     * Log:      storage/logs/Roster/TeamsImport.log
     * Protocol: import_logs → in_corso → successo/errore
     * Schema:   usa SOLO colonne confermate da DESCRIBE import_logs
     *
     * @return array ['created' => int, 'updated' => int]
     */
    public function importTeamsFromApi(?int $seasonYear = null): array
    {
        // ── Logger dedicato ───────────────────────────────────────────────────
        // Path: storage/logs/Squadre/ (convenzione: Squadre/ per squadre, Roster/ per giocatori)
        $logDir = storage_path('logs/Squadre');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logger = Log::build([
            'driver' => 'single',
            'path'   => $logDir . '/SquadreImport.log',
        ]);

        $currentYear  = (int) date('Y');
        $targetSeason = $seasonYear ?? ($currentYear - 1); // start-year convention: 2025 = stagione 2025/2026

        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info("🚀 AVVIO importTeamsFromApi — stagione target: {$targetSeason}");

        // ── Apri record import_logs (solo colonne esistenti) ──────────────────
        $importLogId = DB::table('import_logs')->insertGetId([
            'original_file_name' => "football-data.org /v4/competitions/SA/teams?season={$targetSeason}",
            'import_type'        => 'teams_api',
            'status'             => 'in_corso',
            'rows_processed'     => 0,
            'rows_created'       => 0,
            'rows_updated'       => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
        $logger->info("📋 ImportLog #{$importLogId} creato — status: in_corso");

        // ── API Key ───────────────────────────────────────────────────────────
        $apiKey = config('services.player_stats_api.providers.football_data_org.api_key');
        if (empty($apiKey)) {
            $msg = 'FOOTBALL_DATA_API_KEY non configurata in .env';
            $logger->error("❌ {$msg}");
            DB::table('import_logs')->where('id', $importLogId)->update([
                'status'     => 'errore',
                'details'    => $msg,
                'updated_at' => now(),
            ]);
            throw new \Exception($msg);
        }

        // ── Chiamata API ──────────────────────────────────────────────────────
        $url = "https://api.football-data.org/v4/competitions/SA/teams?season={$targetSeason}";
        $logger->info("🌐 GET {$url}");

        try {
            $response = Http::withHeaders(['X-Auth-Token' => $apiKey])->timeout(20)->get($url);
        } catch (\Throwable $e) {
            $logger->error("❌ Eccezione HTTP: " . $e->getMessage());
            DB::table('import_logs')->where('id', $importLogId)->update([
                'status'     => 'errore',
                'details'    => $e->getMessage(),
                'updated_at' => now(),
            ]);
            throw new \Exception("Errore connessione football-data.org: " . $e->getMessage());
        }

        if ($response->failed()) {
            $msg = "HTTP {$response->status()} da football-data.org";
            $logger->error("❌ {$msg}");
            DB::table('import_logs')->where('id', $importLogId)->update([
                'status'     => 'errore',
                'details'    => $msg,
                'updated_at' => now(),
            ]);
            throw new \Exception($msg);
        }

        $fullResponse = $response->json();
        $teams = $fullResponse['teams'] ?? [];
        $logger->info("✅ Risposta OK — Squadre ricevute: " . count($teams));

        // ── DUMP DIAGNOSTICO — oggetto competition e season dall'API ─────────
        $competition = $fullResponse['competition'] ?? [];
        $season      = $fullResponse['season']      ?? $fullResponse['filters'] ?? [];
        $logger->info("📡 COMPETITION: " . json_encode($competition, JSON_UNESCAPED_UNICODE));
        $logger->info("📡 SEASON/FILTERS: " . json_encode($season, JSON_UNESCAPED_UNICODE));
        $logger->info("📡 PRIME 3 SQUADRE RAW:");
        foreach (array_slice($teams, 0, 3) as $t) {
            $logger->info("   " . json_encode([
                'id'        => $t['id']       ?? null,
                'name'      => $t['name']     ?? null,
                'shortName' => $t['shortName'] ?? null,
                'tla'       => $t['tla']       ?? null,
            ], JSON_UNESCAPED_UNICODE));
        }
        // ── FINE DUMP ─────────────────────────────────────────────────────────

        if (empty($teams)) {
            $msg = "Nessuna squadra per stagione {$targetSeason}";
            $logger->warning("⚠️ {$msg}");
            DB::table('import_logs')->where('id', $importLogId)->update([
                'status'     => 'warning',
                'details'    => $msg,
                'updated_at' => now(),
            ]);
            return ['created' => 0, 'updated' => 0];
        }

        // ── Upsert squadre ────────────────────────────────────────────────────
        $logger->info(str_repeat('─', 55));
        $created = 0;
        $updated = 0;

        foreach ($teams as $apiTeam) {
            $apiId     = $apiTeam['id']       ?? null;
            $name      = $apiTeam['name']      ?? null;
            $shortName = $apiTeam['shortName'] ?? $apiTeam['tla'] ?? $name;
            $crest     = $apiTeam['crest']     ?? null;
            $tla       = $apiTeam['tla']       ?? null;

            if (!$apiId || !$name) {
                $logger->warning("⚠️ Saltata — id/name mancanti");
                continue;
            }

            // 1. Ricerca Intelligente (Prevenzione Duplicati/Cadaveri)
            // A. Cerco per api_id (già ufficiale)
            $team = \App\Models\Team::where('api_id', $apiId)->first();

            if (!$team) {
                // B. Cerco un "Orfano" (record creato da FBref senza ancora api_id)
                $orphanId = $this->findOrphanIdByName($name);
                if ($orphanId) {
                    $team = \App\Models\Team::find($orphanId);
                    $logger->info("♻️  [ORPHAN ADOPTION] Team '{$name}' (ID: {$team->id}) trovato come orfano. Aggancio api_id: {$apiId}");
                }
            }

            // 2. Upsert Master Team (usa l'ID trovato o ne crea uno nuovo)
            $team = \App\Models\Team::updateOrCreate(
                ['id' => $team?->id ?? null],
                [
                    'api_id'     => $apiId,
                    'name'       => $name,
                    'short_name' => $shortName,
                    'logo_url'   => $crest,
                    'tla'        => $tla,
                ]
            );
            
            if ($team->wasRecentlyCreated) {
                $created++;
                $logger->info("✅ CREATA (Master): {$name} (API ID: {$apiId})");
            } else {
                $updated++;
                $logger->info("🔄 AGGIORNATA (Master): [{$team->id}] {$name} (API ID: {$apiId})");
            }

            // 2. Upsert Snapshot (team_season)
            $seasonModel = \App\Models\Season::where('season_year', $targetSeason)->first();
            if ($seasonModel) {
                \App\Models\TeamSeason::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'season_id' => $seasonModel->id,
                    ],
                    [
                        'league_id' => League::where('api_id', 2019)->first()?->id ?? 1,
                        'is_active' => true,
                    ]
                );
            }
        }

        $total = $created + $updated;
        $logger->info(str_repeat('─', 55));
        $logger->info("🏁 FINE — Master Creati: {$created} | Master Aggiornati: {$updated} | Totale: {$total}");

        // ── Chiudi import_logs (solo colonne esistenti) ───────────────────────
        DB::table('import_logs')->where('id', $importLogId)->update([
            'status'         => 'successo',
            'rows_processed' => $total,
            'rows_created'   => $created,
            'rows_updated'   => $updated,
            'details'        => "Stagione {$targetSeason}: {$created} master create/rilevate, {$updated} master aggiornate",
            'updated_at'     => now(),
        ]);
        $logger->info("📋 ImportLog #{$importLogId} — status: successo");
        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return ['created' => $created, 'updated' => $updated];
    }
}
