<?php

namespace App\Console\Commands\Extraction;

use App\Models\ImportLog;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;
use App\Services\RoleNormalizationService;
use App\Services\TeamDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlayersHistoricalSync extends Command
{
    protected $signature = 'players:historical-sync
                            {--season=      : Stagione specifica YYYY da processare (es. 2024). Default: tutte (2021-2025)}
                            {--threshold=90 : Soglia di similitudine per il matching (default 90)}
                            {--team=        : ID API o locale della squadra specifica per test mirati}
                            {--force        : Riprocessa anche i calciatori già matchati}';

    protected $description = 'Sincronizzazione Multi-Stagione delle rose (API -> DB). Navigazione storica per risolvere i trasferimenti.';

    protected TeamDataService          $teamDataService;
    protected RoleNormalizationService $roleNormalizer;
    protected \Psr\Log\LoggerInterface $syncLogger;
    protected array                    $orphanRegistryByRole = [];

    public function __construct(TeamDataService $teamDataService)
    {
        parent::__construct();
        $this->teamDataService = $teamDataService;
        $this->roleNormalizer  = new RoleNormalizationService();
    }

    public function handle(): int
    {
        // ── Setup Logging ────────────────────────────────────────────────────
        $logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        $this->syncLogger = Log::build(['driver' => 'single', 'path' => $logPath]);
        
        $this->syncLogger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->syncLogger->info('🔄 AVVIO SINCRONIZZAZIONE STORICA (Step 7 - New Arch)');
        $this->syncLogger->info('🕐 Ora: ' . now()->format('Y-m-d H:i:s'));

        // ── Determinazione Stagioni ──────────────────────────────────────────
        $requestedSeason = $this->option('season');
        $threshold       = (float) ($this->option('threshold') ?? 90.0);
        $forceMode       = $this->option('force') ?? false;

        // Pulizia Cache Progresso all'avvio
        Cache::forget('sync_rose_progress');

        if (!$requestedSeason) {
            $this->error("Errore: il parametro --season=YYYY è obbligatorio (es. --season=2024).");
            $this->syncLogger->error("Tentativo di avvio senza stagione specificata.");
            return Command::FAILURE;
        }

        $seasonModel = Season::where('season_year', $requestedSeason)->first();

        if (!$seasonModel) {
            $this->error("Stagione {$requestedSeason} non trovata nel database.");
            return Command::FAILURE;
        }

        // ── Pre-caricamento Anagrafica Orfani (Ottimizzazione L3) ────────────
        $this->orphanRegistryByRole = Player::withTrashed()
            ->whereNull('api_football_data_id')
            ->select('id', 'name', 'role')
            ->get()
            ->groupBy('role')
            ->toArray();

        $this->info("   - Caricati " . Player::whereNull('api_football_data_id')->count() . " orfani.");

        $this->processSeason($seasonModel, $threshold, $forceMode);

        $this->info("\n🏁 Sincronizzazione Stagione {$requestedSeason} Completata!");
        $this->syncLogger->info("🏁 FINE SINCRONIZZAZIONE STAGIONE {$requestedSeason}");
        return Command::SUCCESS;
    }

    private function processSeason(Season $seasonModel, float $threshold, bool $forceMode)
    {
        $year = $seasonModel->season_year;
        $this->info("\n=== 📅 STAGIONE {$year} ===");
        $this->syncLogger->info("\n=== 📅 STAGIONE {$year} ===" . ($forceMode ? ' [FORCE]' : ''));

        // ── Apertura ImportLog ───────────────────────────────────────────────
        $importLog = ImportLog::create([
            'season_id'          => $seasonModel->id,
            'import_type'        => 'sync_rose_api_historical',
            'original_file_name' => "ROSTER_SYNC_{$year}",
            'status'             => 'in_corso',
            'details'            => "[Stagione {$year}] Avvio sincronizzazione...",
        ]);

        $activeTeams = Team::whereNotNull('api_id')
            ->whereHas('teamSeasons', function($q) use ($seasonModel) {
                $q->where('season_id', $seasonModel->id)
                  ->where('league_id', 1); // Filtro rigoroso: Solo Serie A (ID 1)
            });

        if ($this->option('team')) {
            $teamId = $this->option('team');
            $activeTeams->where(function($q) use ($teamId) {
                $q->where('id', $teamId)->orWhere('api_id', $teamId);
            });
        }

        $activeTeams = $activeTeams->get();

        if ($activeTeams->isEmpty()) {
            $msg = "Nessuna squadra attiva con api_id per l'anno {$year}.";
            $this->warn($msg);
            $this->syncLogger->warning($msg);
            $importLog->update(['status' => 'completato', 'details' => 'Nessuna squadra attiva trovata.']);
            return;
        }

        $updatedCount = 0;
        $createdCount = 0;
        $errorCount   = 0;
        $apiFailCount = 0; // Contatore fallimenti 403
        $totalTeams   = $activeTeams->count();

        $this->getOutput()->progressStart($totalTeams);

        foreach ($activeTeams as $index => $team) {
            $this->syncLogger->info("⚽ Processing {$team->name} [{$year}]");

            try {
                // 1. FETCH dall'API con parametro temporale
                $apiUrl = "https://api.football-data.org/v4/teams/{$team->api_id}?season={$year}";
                $this->syncLogger->info("  - [API REQUEST] URL: {$apiUrl}");
                
                $squadFromApi = $this->teamDataService->getSquad($team->api_id, $year);
                
                if (empty($squadFromApi)) {
                    $this->syncLogger->warning("  - Rosa vuota da API.");
                    $this->getOutput()->progressAdvance();
                    continue;
                }

                // 2. RECUPERO ROSTER LOCALE (Listone filtrato per stagione)
                $localRoster = PlayerSeasonRoster::with('player')
                    ->where('team_id', $team->id)
                    ->where('season_id', $seasonModel->id)
                    ->get();

                foreach ($squadFromApi as $playerData) {
                    $res = $this->matchAndSync($playerData, $seasonModel, $team, $localRoster, $threshold, $forceMode);
                    if ($res === 'updated') $updatedCount++;
                    elseif ($res === 'created') $createdCount++;
                }

            } catch (\Exception $e) {
                if ($e->getMessage() === "403_FORBIDDEN_TIER_LIMIT") {
                    $this->syncLogger->warning("  - ⚠️ Skip per limiti abbonamento (403 Forbidden)");
                    $apiFailCount++;
                    $this->getOutput()->progressAdvance();
                    continue; 
                }
                $this->syncLogger->error("  - ❌ Errore inaspettato per {$team->name}: " . $e->getMessage());
                $errorCount++;
            }

            // Update Cache Progress for UI
            $pct = round((($index + 1) / $totalTeams) * 100);
            Cache::put('sync_rose_progress', [
                'running' => true,
                'percent' => $pct,
                'label'   => "Sincronizzazione in corso ({$year})",
                'team'    => $team->short_name ?? $team->name,
                'done'    => (($index + 1) === $totalTeams),
                'log_id'  => $importLog->id,
            ], now()->addMinutes(10));

            $this->getOutput()->progressAdvance();
            
            // Rate limit (10 req/min free plan -> ~6sec)
            if (($index + 1) < $totalTeams) {
                usleep(config('services.player_stats_api.providers.football_data_org.delay', 7000000));
            }
        }

        $this->getOutput()->progressFinish();

        // Aggiorna log finale
        $finalStatus = 'successo';
        $finalDetail = "[Stagione {$year}] Match: {$updatedCount}, Nuovi: {$createdCount}, Errori: {$errorCount}";
        
        if ($apiFailCount > 0 && ($updatedCount + $createdCount) === 0) {
            $finalStatus = ($apiFailCount === $totalTeams) ? 'errore' : 'parziale';
            $finalDetail .= " | API LIMIT: {$apiFailCount} squadre fallite (Piano limitato)";
        }

        $importLog->update([
            'status'         => $finalStatus,
            'rows_processed' => $updatedCount + $createdCount,
            'rows_created'   => $createdCount,
            'rows_updated'   => $updatedCount,
            'details'        => $finalDetail,
        ]);
        
        $this->syncLogger->info("🏁 FINE STAGIONE {$year}: Match={$updatedCount}, Nuovi={$createdCount}");
    }

    private function matchAndSync(array $playerData, Season $season, Team $team, $localRoster, float $threshold, bool $forceMode)
    {
        $apiId   = $playerData['id'];
        $apiName = $playerData['name'];

        // Normalizzazione ruoli
        $rawApiRole      = ['position' => $playerData['position'] ?? null];
        $normalizedRoles = $this->roleNormalizer->normalize($rawApiRole, 'football_data_api');
        $playerMainRole  = $normalizedRoles['role_main'];
        $apiDetailedPos  = $normalizedRoles['detailed_position'];

        // 1. L1: Match per API ID (Registry Truth)
        $player = Player::withTrashed()->where('api_football_data_id', $apiId)->first();
        if ($player) {
            $this->syncLogger->info("  - [L1] Hit ID {$player->id}: '{$apiName}'");
            if ($player->trashed()) $player->restore();
            $this->updateRegistry($player, $playerData, $apiDetailedPos, $season, $team);
            $this->updateRoster($player, $season, $team, $playerMainRole, $apiDetailedPos);
            return 'updated';
        }

        // 2. L2: Match Locale per Nome (Stessa Squadra)
        $match = $this->findLocalMatch($apiName, $localRoster, $threshold);
        if ($match) {
            $player = Player::withTrashed()->find($match['player_id']);
            if ($player) {
                $this->syncLogger->info("  - [L2] Match Nome: '{$apiName}' -> '{$match['local_name']}' (ID: {$player->id})");
                $this->updateRegistry($player, $playerData, $apiDetailedPos, $season, $team);
                $this->updateRoster($player, $season, $team, $playerMainRole, $apiDetailedPos);
                return 'updated';
            }
        }

        // 3. L3: Global Name Match (Safe) - Cerca tra orfani evitando overlap di squadra nello stesso anno
        $globalMatch = $this->findSafeGlobalMatch($apiName, $playerMainRole, $season, $team, $threshold);
        if ($globalMatch) {
            $player = Player::withTrashed()->find($globalMatch['player_id']);
            if ($player) {
                $this->syncLogger->info("  - [L3] Safe Global Match: '{$apiName}' -> '{$globalMatch['local_name']}' (ID: {$player->id}, Score: {$globalMatch['pct']}%)");
                $this->updateRegistry($player, $playerData, $apiDetailedPos, $season, $team);
                $this->updateRoster($player, $season, $team, $playerMainRole, $apiDetailedPos);
                return 'updated';
            }
        }

        // 4. L4: Create New (with SAFETY Catch)
        // Controllo secco su ID API nel Registro per prevenire duplicati
        $safetyPlayer = Player::withTrashed()->where('api_football_data_id', $apiId)->first();
        if ($safetyPlayer) {
            $this->syncLogger->warning("  - 🛡️ [L4 SAFETY] API ID {$apiId} trovato nel DB (ID {$safetyPlayer->id}). Evitato duplicato per '{$apiName}'.");
            if ($safetyPlayer->trashed()) $safetyPlayer->restore();
            $this->updateRegistry($safetyPlayer, $playerData, $apiDetailedPos, $season, $team);
            $this->updateRoster($safetyPlayer, $season, $team, $playerMainRole, $apiDetailedPos);
            return 'updated';
        }

        $this->syncLogger->warning("  - ⛔ [L4_REJECTED] Match Fallito per '{$apiName}'. Nessun calciatore nel database corrisponde con un punteggio >= {$threshold}%.");
        $this->syncLogger->info("    - Procedo alla creazione di un NUOVO record nel registro (Step 7 - L4).");
        $this->createNewPlayer($playerData, $season, $team, $playerMainRole, $apiDetailedPos);
        return 'created';
    }

    private function findLocalMatch(string $apiName, $localRoster, float $threshold): ?array
    {
        $bestMatch = null;
        $maxPct    = 0;

        foreach ($localRoster as $item) {
            if (!$item->player) continue;

            $pct = $this->calculateSimilarity($item->player->name, $apiName);

            if ($pct >= $threshold && $pct > $maxPct) {
                $maxPct = $pct;
                $bestMatch = [
                    'local_name' => $item->player->name,
                    'player_id'  => $item->player_id,
                    'pct'        => round($pct, 1),
                ];
            }
        }

        return $bestMatch;
    }

    private function findSafeGlobalMatch(string $apiName, ?string $role, Season $season, Team $currentTeam, float $threshold): ?array
    {
        $bestMatch = null;
        $maxPct    = 0;
        $candidates = [];

        // Se il ruolo è disponibile, cerchiamo prima in quel cassetto, altrimenti su tutto il pool
        $shardsToScan = ($role && isset($this->orphanRegistryByRole[$role])) 
            ? [$role => $this->orphanRegistryByRole[$role]] 
            : $this->orphanRegistryByRole;

        foreach ($shardsToScan as $roleKey => $roleShard) {
            foreach ($roleShard as $p) {
                $pct = $this->calculateSimilarity($p['name'], $apiName);

                // Salviamo candidati vicini alla soglia per il log analitico
                if ($pct > 50) {
                    $candidates[] = ['name' => $p['name'], 'pct' => round($pct, 1)];
                }

                if ($pct >= $threshold && $pct > $maxPct) {
                    $maxPct = $pct;
                    $bestMatch = [
                        'local_name' => $p['name'],
                        'player_id'  => $p['id'],
                        'pct'        => round($pct, 1),
                    ];
                }
            }
        }

        if (!$bestMatch && !empty($candidates)) {
            // Ordiniamo i candidati per score e prendiamo i top 3
            usort($candidates, fn($a, $b) => $b['pct'] <=> $a['pct']);
            $topCandidates = array_slice($candidates, 0, 3);
            
            $logMsg = "    🔍 [MATCH_ANALYSIS] '{$apiName}' non matchato. Candidati vicini scartati: ";
            foreach ($topCandidates as $c) {
                $logMsg .= "'{$c['name']}' ({$c['pct']}%), ";
            }
            $this->syncLogger->info(rtrim($logMsg, ', ') . ". [Soglia richiesta: {$threshold}%]");
        }

        return $bestMatch;
    }

    private function updateRegistry(Player $player, array $playerData, ?array $apiDetailedPos, Season $season, Team $team)
    {
        $attrs = [
            'api_football_data_id' => $playerData['id'],
            'date_of_birth'        => isset($playerData['dateOfBirth']) 
                                      ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d') 
                                      : $player->date_of_birth,
        ];

        // Se stiamo processando la stagione CORRENTE, aggiorniamo anche la squadra nel registro principale
        if ($season->is_current) {
            $attrs['team_id']   = $team->id;
            $attrs['team_name'] = $team->name;
        }

        // Detailed Position Merge
        $currentPos = $player->detailed_position ?? [];
        if (!empty($apiDetailedPos)) {
            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
            sort($merged);
            if ($merged !== $currentPos) $attrs['detailed_position'] = $merged;
        }

        $player->update($attrs);
    }

    private function updateRoster(Player $player, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        // Aggiorna o crea il record in player_season_roster per questa specifica stagione
        $roster = PlayerSeasonRoster::firstOrNew([
            'player_id' => $player->id,
            'season_id' => $season->id,
        ]);

        if ($roster->exists && $roster->team_id !== $team->id) {
            $oldTeamName = $roster->team?->name ?? 'Sconosciuta';
            $this->syncLogger->info("    - 🔄 [TRANSFER] Rilevato cambio squadra per {$player->name}: {$oldTeamName} -> {$team->name}");
        }

        $roster->team_id = $team->id;
        
        if ($playerMainRole && !$roster->role) {
            $roster->role = $playerMainRole;
        }

        $currentPos = $roster->detailed_position ?? [];
        if (!empty($apiDetailedPos)) {
            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
            sort($merged);
            $roster->detailed_position = $merged;
        }

        $roster->save();
    }

    private function createNewPlayer(array $playerData, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        $player = Player::create([
            'api_football_data_id' => $playerData['id'],
            'name'                 => $playerData['name'],
            'date_of_birth'        => isset($playerData['dateOfBirth']) ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d') : null,
            'role'                 => $playerMainRole,
            'detailed_position'    => $apiDetailedPos,
        ]);

        PlayerSeasonRoster::create([
            'player_id'         => $player->id,
            'season_id'         => $season->id,
            'team_id'           => $team->id,
            'role'              => $playerMainRole,
            'detailed_position' => $apiDetailedPos,
            'initial_quotation' => 0,
            'fanta_quotation'   => 0,
            'fvm'               => 0,
        ]);
        
        $this->syncLogger->info("  - ✅ [L4] CREAZIONE CALCIATORE: '{$player->name}' (API ID: {$playerData['id']}). Motivo: Nessun candidato idoneo trovato.");
    }

    private function calculateSimilarity(string $name1, string $name2): float
    {
        $tokens1 = $this->getNormalizedTokens($name1);
        $tokens2 = $this->getNormalizedTokens($name2);
        
        if (empty($tokens1) || empty($tokens2)) return 0;

        $shortSet = (count($tokens1) <= count($tokens2)) ? $tokens1 : $tokens2;
        $longSet  = ($shortSet === $tokens1) ? $tokens2 : $tokens1;
        $total    = count($shortSet);
        $matches  = 0;

        foreach ($shortSet as $token) {
            foreach ($longSet as $k => $candidate) {
                if ($token === $candidate || 
                    (str_ends_with($token, '.') && str_starts_with($candidate, rtrim($token, '.'))) ||
                    (str_ends_with($candidate, '.') && str_starts_with($token, rtrim($candidate, '.')))
                ) {
                    $matches++;
                    unset($longSet[$k]);
                    continue 2;
                }
            }
            
            // Fuzzy fallback
            $bestFuzzy = 0;
            $bestK = -1;
            foreach ($longSet as $k => $candidate) {
                similar_text($token, $candidate, $pct);
                if ($pct > 80 && $pct > $bestFuzzy) {
                    $bestFuzzy = $pct;
                    $bestK = $k;
                }
            }
            if ($bestK !== -1) {
                $matches += ($bestFuzzy / 100);
                unset($longSet[$bestK]);
            }
        }

        return ($matches / $total) * 100;
    }

    private function getNormalizedTokens(string $name): array
    {
        // Normalizzazione aggressiva (inclusi accenti via Str::ascii)
        $n = Str::ascii(strtolower(trim($name)));
        $n = str_replace(["'", '-'], ' ', $n);
        $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return array_values(array_filter(explode(' ', trim($n))));
    }
}
