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

        // ── Carica parametri dalla config (Modello Ibrido 70/30) ──
        $cfg       = config('projection_settings.tiers', []);
        $hybridCfg = $cfg['hybrid'] ?? [];
        $isEnabled = $hybridCfg['enabled'] ?? false;

        // Parametri Traccia STORICA
        $histCfg      = $hybridCfg['historic_track'] ?? [];
        $histLookback = (int) ($histCfg['lookback'] ?? 4);
        $histWeights  = $histCfg['weights']  ?? [12, 4, 2, 1];
        $histDivisor  = (float) ($histCfg['divisor']  ?? 19.0);

        // Parametri Traccia MOMENTUM
        $momCfg       = $hybridCfg['momentum_track'] ?? [];
        $momLookback  = (int) ($momCfg['lookback'] ?? 2);
        $momWeights   = $momCfg['weights']   ?? [10, 4];
        $momDivisor   = (float) ($momCfg['divisor']   ?? 14.0);

        // Pesi Fusione
        $wHist = (float) ($hybridCfg['weights']['historic'] ?? 0.70);
        $wMom  = (float) ($hybridCfg['weights']['momentum'] ?? 0.30);

        $thresholdsRaw = $cfg['thresholds'] ?? [
            't1' => 7.5,
            't2' => 9.5,
            't3' => 12.5,
            't4' => 13.5,
        ];
        
        $thresholds = [
            1 => $thresholdsRaw['t1'],
            2 => $thresholdsRaw['t2'],
            3 => $thresholdsRaw['t3'],
            4 => $thresholdsRaw['t4'],
        ];

        // Definiamo il range massimo di stagioni da analizzare
        $maxLookback = max($histLookback, $momLookback);
        $currentYear = \App\Helpers\SeasonHelper::getCurrentSeason();
        $lastConcluded = $currentYear - 1;
        $seasons = [];
        for ($i = 0; $i < $maxLookback; $i++) {
            $seasons[] = $lastConcluded - $i;
        }

        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("🏆  HYBRID TIER CALCULATION (70% HIST / 30% MOM)");
        $logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $logger->info("⚙️  Storico    : {$histLookback} stagioni | Pesi: [" . implode(',', $histWeights) . "] | Divisore: {$histDivisor}");
        $logger->info("⚙️  Momentum   : {$momLookback} stagioni | Pesi: [" . implode(',', $momWeights) . "] | Divisore: {$momDivisor}");
        $logger->info("⚙️  Fusione    : " . ($wHist * 100) . "% Storico / " . ($wMom * 100) . "% Momentum");
        $logger->info("⚙️  Soglie     : T1:{$thresholds[1]} T2:{$thresholds[2]} T3:{$thresholds[3]} T4:{$thresholds[4]}");
        $logger->info(str_repeat('─', 70));

        $teams      = \App\Models\Team::all();
        $updated    = 0;
        $skipped    = 0;
        $upsertData = [];

        // Pesi Fattore Potenza (Componente Cinetica)
        $w_pts = (float) ($cfg['weights']['points'] ?? 0.60);
        $w_gf  = (float) ($cfg['weights']['goals_for'] ?? 0.28);
        $w_gs  = (float) ($cfg['weights']['goals_against'] ?? 0.12);

        // Moltiplicatori Serie B (Penalità Lineare)
        $cf_pts = (float) ($cfg['serie_b_multipliers']['points'] ?? 1.60);
        $cf_gf  = (float) ($cfg['serie_b_multipliers']['goals_for'] ?? 1.60);
        $cf_gs  = (float) ($cfg['serie_b_multipliers']['goals_against'] ?? 1.00);

        foreach ($teams as $team) {
            $histWeightedSum = 0.0;
            $momWeightedSum  = 0.0;
            $details = [];

            foreach ($seasons as $idx => $year) {
                $standing = DB::table('team_historical_standings')
                    ->where('team_id', $team->id)
                    ->where('season_year', $year)
                    ->first();

                $isSerieA = ($standing && isset($standing->league_name) && $standing->league_name === 'Serie A');
                
                // --- 1. CALCOLO SCORE STAGIONALE UNIFICATO (CON MALUS E HARD CAP) ---
                if (!$standing) {
                    $seasonalScore = 20.0; // Peggior punteggio possibile (Hard Cap)
                } else {
                    $rawPts = $standing->points ?? 0;
                    $rawGf  = $standing->goals_for ?? 0;
                    $rawGs  = $standing->goals_against ?? 0;
                    $played = $standing->played_games ?? $standing->played ?? 38;
                    $maxPts = $played > 0 ? $played * 3 : 114;

                    $ptsComp = (1.0 - (min(1.0, $rawPts / $maxPts))) * 20.0;
                    $gfComp  = (1.0 - (min(1.0, $rawGf  / 90.0))) * 20.0;
                    $gsComp  = (min(1.0, $rawGs  / 75.0)) * 20.0;

                    // Applicazione Malus all'origine (Punti e Gol Fatti) se Serie B
                    $ptsEff = $isSerieA ? $ptsComp : ($ptsComp * $cf_pts);
                    $gfEff  = $isSerieA ? $gfComp  : ($gfComp  * $cf_gf);
                    $gsEff  = $isSerieA ? $gsComp  : ($gsComp  * $cf_gs);

                    $seasonalScore = min(20.0, ($ptsEff * $w_pts) + ($gfEff * $w_gf) + ($gsEff * $w_gs));
                }

                // --- 2. ACCUMULO IN ENTRAMBE LE TRACCE (ENTRAMBE USANO LO SCORE PENALIZZATO) ---
                if ($idx < $histLookback) {
                    $histWeightedSum += ($seasonalScore * ($histWeights[$idx] ?? 1));
                }
                if ($idx < $momLookback) {
                    $momWeightedSum  += ($seasonalScore * ($momWeights[$idx] ?? 1));
                }

                $details[] = sprintf("%d(%s:%.2f)", $year, $standing ? ($isSerieA ? 'A' : 'B') : '?', $seasonalScore);
            }

            // --- 3. FUSIONE IBRIDA ---
            $s_hist = $histWeightedSum / $histDivisor;
            $s_mom  = $momWeightedSum  / $momDivisor;
            $finalScore = ($s_hist * $wHist) + ($s_mom * $wMom);

            // --- 4. TREND PENALTY (Solo su componente storica se necessario) ---
            $trendMalus = 1.00;
            // ... (manteniamo la logica esistente se utile, basata su posizioni reali serie A)
            $posHistory = [];
            foreach (array_slice($seasons, 0, 3) as $s) {
                $st = DB::table('team_historical_standings')
                    ->where('team_id', $team->id)
                    ->where('season_year', $s)
                    ->where('league_name', 'Serie A')
                    ->first();
                if ($st && $st->position > 0) $posHistory[] = $st->position;
            }
            if (count($posHistory) >= 3 && $posHistory[0] > $posHistory[1] && $posHistory[1] > $posHistory[2]) {
                $trendMalus = (float) ($cfg['trend_penalty'] ?? 1.05);
                $finalScore = $finalScore * $trendMalus;
            }

            $tier = $this->assignTierByThresholds($finalScore, $thresholds);

            $logger->info(sprintf(
                "📌 %-20s | S_Hist: %5.2f | S_Mom: %5.2f | Final: %5.2f%s → TIER %d",
                $team->name, $s_hist, $s_mom, $finalScore, ($trendMalus > 1 ? " (Trend!)" : ""), $tier
            ));
            
            $upsertData[] = [
                'id' => $team->id,
                'tier_globale' => $tier,
                'posizione_media_storica' => round($finalScore, 4),
            ];
            $updated++;
        }

        foreach ($upsertData as $row) {
            DB::table('teams')->where('id', $row['id'])->update([
                'tier_globale' => $row['tier_globale'],
                'posizione_media_storica' => $row['posizione_media_storica'],
            ]);
        }

        DB::table('import_logs')->insert([
            'original_file_name' => 'teams:update-tiers',
            'import_type'        => 'team_tier_update',
            'status'             => 'success',
            'details'            => json_encode([
                'engine' => 'hybrid_70_30_v1',
                'calibration' => 'MAE=1.05 Affinity=95.0% (GoldStandard-GridSearch 2026-04-08)',
            ]),
            'rows_processed'     => $teams->count(),
            'rows_updated'       => $updated,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $logger->info(str_repeat('─', 70));
        $logger->info("--- ✅ CALCOLO IBRIDO COMPLETATO: {$updated} aggiornati ---");

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
     * @param  int|null $season L'anno della stagione (es: 2024)
     * @return array           Array di giocatori: [id, name, position, dateOfBirth, ...]
     */
    public function getSquad(int $apiTeamId, ?int $season = null): array
    {
        $apiKey = config('services.player_stats_api.providers.football_data_org.api_key');

        if (empty($apiKey)) {
            \Illuminate\Support\Facades\Log::error('TeamDataService::getSquad — FOOTBALL_DATA_API_KEY non configurata in .env');
            return [];
        }

        $url = "https://api.football-data.org/v4/teams/{$apiTeamId}";
        if ($season) {
            $url .= "?season={$season}";
        }

        // Logga l'URL per permettere il debug manuale (Postman)
        // Usiamo un log generico, il comando lo catturerà se configurato o lo vedremo nei log laravel
        \Illuminate\Support\Facades\Log::info("[API REQUEST] Squad URL: {$url}");

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(15)->get($url);

            if ($response->failed()) {
                // Gestione specifica per il 403 Forbidden (limiti tier gratuito)
                if ($response->status() === 403) {
                     throw new \Exception("403_FORBIDDEN_TIER_LIMIT", 403);
                }
                
                \Illuminate\Support\Facades\Log::warning(
                    "TeamDataService::getSquad — HTTP {$response->status()} per teamId={$apiTeamId}"
                );
                return [];
            }

            $data = $response->json();
            return $data['squad'] ?? [];

        } catch (\Throwable $e) {
            if ($e->getMessage() === "403_FORBIDDEN_TIER_LIMIT") {
                throw $e;
            }

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
                        'is_active' => $seasonModel->isActuallyCurrent(),
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
