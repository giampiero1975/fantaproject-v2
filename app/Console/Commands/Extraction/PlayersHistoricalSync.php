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
use App\Traits\FindsPlayerByName;

class PlayersHistoricalSync extends Command
{
    protected $signature = 'players:historical-sync 
                            {--season= : L\'anno della stagione (es. 2021, 2022, 2023)} 
                            {--team= : Nome o ID della squadra (opzionale)}
                            {--threshold=78 : Soglia di similitudine minima per Match Globale (L3)}
                            {--exclude-teams= : Lista di ID team da escludere (separati da virgola)}
                            {--bulk : Esegue il recupero massivo delle rose via endpoint competizione (consigliato)}
                            {--debug : Mostra log verbosi del matching}
                            {--force        : Riprocessa anche i calciatori già matchati}';

    protected $description = 'Sincronizzazione Multi-Stagione delle rose (API -> DB). Navigazione storica per risolvere i trasferimenti.';

    protected TeamDataService          $teamDataService;
    protected RoleNormalizationService $roleNormalizer;
    protected \Psr\Log\LoggerInterface $syncLogger;
    protected array                    $registryMap = [];
    protected array                    $slugMap     = [];
    protected array                    $currentSeasonRosterMap = [];
    
    use FindsPlayerByName;

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

        // ── [ERP-FAST] Pre-caricamento Anagrafica Globale ──────────────────
        $this->info("🚀 [ERP-FAST] Caricamento anagrafica in RAM...");
        $allPlayers = Player::withTrashed()->get();
        foreach ($allPlayers as $p) {
            if ($p->api_football_data_id) {
                $this->registryMap[$p->api_football_data_id] = $p;
            }
            $slug = Str::slug($p->name);
            $this->slugMap[$slug][] = $p; 
        }
        $this->info("   - Caricati " . $allPlayers->count() . " calciatori in memoria.");

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
        $apiFailCount = 0; 
        $matchedCount = 0; 
        $totalTeams   = $activeTeams->count();

        // --- 🚀 [BULK LOAD] Recupero Massivo Rose ---
        $bulkSquads = [];
        if ($this->option('bulk')) {
            $this->info("🚀 [BULK_LOAD] Inizio caricamento massivo rose per la stagione {$year}...");
            $bulkSquads = $this->teamDataService->getCompetitionSquads($year, 'SA');
            if (!empty($bulkSquads)) {
                $this->info("✅ [BULK_LOAD] Caricate rose per " . count($bulkSquads) . " squadre.");
                $this->syncLogger->info("🚀 [BULK_LOAD] Caricate rose per " . count($bulkSquads) . " squadre via endpoint competizione.");
            } else {
                $this->warn("⚠️ [BULK_LOAD] Impossibile caricare rose in bulk. Il sistema userà le chiamate individuali.");
            }
        }

        $this->getOutput()->progressStart($totalTeams);

        foreach ($activeTeams as $index => $team) {
            $this->syncLogger->info("⚽ Processing {$team->name} [{$year}]");

            // --- 🚫 ESCLUSIONE TEAM (403 Safeguard) ---
            $excludeIds = $this->option('exclude-teams') ? explode(',', $this->option('exclude-teams')) : [];
            if (in_array($team->id, $excludeIds)) {
                $this->syncLogger->warning("  - ⏩ [SKIP] Team '{$team->name}' (ID: {$team->id}) escluso per opzione --exclude-teams.");
                $this->getOutput()->progressAdvance();
                continue;
            }

            try {
                // 1. RECUPERO ROSA (Bulk o Individuale)
                $squadFromApi = $bulkSquads[$team->api_id] ?? [];
                
                if (empty($squadFromApi)) {
                    $apiUrl = "https://api.football-data.org/v4/teams/{$team->api_id}?season={$year}";
                    $this->syncLogger->info("  - [FALLBACK] ID '{$team->api_id}' non trovato in bulk o vuoto. Chiamata individuale: {$apiUrl}");
                    $squadFromApi = $this->teamDataService->getSquad($team->api_id, $year);
                } else {
                    $this->syncLogger->info("  - [BULK_DATA] Utilizzo dati pre-caricati per '{$team->name}' (" . count($squadFromApi) . " giocatori).");
                }
                
                if (empty($squadFromApi)) {
                    $this->syncLogger->warning("  - [EMPTY] Rosa vuota da API (Individuale + Bulk falliti).");
                    $this->getOutput()->progressAdvance();
                    continue;
                }

                // 2. [ERP-FAST] RECUPERO ROSTER LOCALE IN-MEMORY
                $this->currentSeasonRosterMap = PlayerSeasonRoster::where('season_id', $seasonModel->id)
                    ->get()
                    ->groupBy('team_id')
                    ->map(fn($group) => $group->keyBy('player_id'))
                    ->toArray();

                foreach ($squadFromApi as $playerData) {
                    $res = $this->matchAndSync($playerData, $seasonModel, $team, $threshold, $forceMode);
                    if ($res === 'updated') $updatedCount++;
                    elseif ($res === 'created') $createdCount++;
                    elseif ($res === 'matched') $matchedCount++;
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
        $finalDetail = "[Stagione {$year}] Aggiornati: {$updatedCount}, Creati: {$createdCount}, Errori: {$errorCount}. Processati " . ($updatedCount + $createdCount + $matchedCount) . " calciatori API.";
        
        if ($apiFailCount > 0 && ($updatedCount + $createdCount + $matchedCount) === 0) {
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

    private function matchAndSync(array $playerData, Season $season, Team $team, float $threshold, bool $forceMode)
    {
        $apiId   = $playerData['id'];
        $apiName = $playerData['name'];

        // Normalizzazione ruoli
        $rawApiRole      = ['position' => $playerData['position'] ?? null];
        $normalizedRoles = $this->roleNormalizer->normalize($rawApiRole, 'football_data_api');
        $playerMainRole  = $normalizedRoles['role_main'];
        $apiDetailedPos  = $normalizedRoles['detailed_position'];

        // 1. [ERP-FAST] Matching In-Memory (L1, L2, L3)
        $player = $this->findPlayerInMaps(
            $this->registryMap, 
            $this->slugMap, 
            ['name' => $apiName, 'api_id' => $apiId],
            $team->id,
            $playerMainRole
        );

        if ($player) {
            $this->syncLogger->info("  - [ERP-FAST] Hit found for '{$apiName}' (ID: {$player->id})");
            if ($player->trashed()) $player->restore();
            
            $c1 = $this->updateRegistry($player, $playerData, $apiDetailedPos, $season, $team);
            $c2 = $this->updateRoster($player, $season, $team, $playerMainRole, $apiDetailedPos);
            
            return ($c1 || $c2) ? 'updated' : 'matched';
        }

        // 4. [MATCH O NULLA] Policy
        $this->syncLogger->warning("  - ⛔ [MATCH_FAILED] '{$apiName}' (API ID: {$apiId}) non trovato in anagrafica.");
        $this->syncLogger->info("    - [SKIP] Policy 'Match o Nulla' attiva: non creo nuovi record zombie da API.");
        
        return 'skipped';
    }

    private function updateRegistry(Player $player, array $playerData, ?array $apiDetailedPos, Season $season, Team $team): bool
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

        $player->fill($attrs);
        $wasDirty = $player->isDirty();
        $player->save();

        return $wasDirty;
    }

    private function updateRoster(Player $player, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos): bool
    {
        // 1. [ERP-FAST] Verifica presenza in qualsiasi squadra (In-Memory)
        $existingRoster = null;
        foreach ($this->currentSeasonRosterMap as $tId => $rosters) {
            if (isset($rosters[$player->id])) {
                $existingRoster = $rosters[$player->id];
                break;
            }
        }

        if ($existingRoster && $existingRoster->team_id !== $team->id) {
            // --- 🛡️ LOGICA PROPRIETÀ (CROSS-TEAM OWNERSHIP) ---
            if ($existingRoster->parent_team_id !== $team->id) {
                if ($existingRoster->exists) {
                    $existingRoster->parent_team_id = $team->id;
                    $existingRoster->save();
                }

                if (!$player->parent_team_id) {
                    $player->update(['parent_team_id' => $team->id]);
                }

                $this->syncLogger->info("    - 🛡️ [PROPERTY_LINK] {$player->name} appartiene a {$team->short_name} via API.");
                return true;
            }
            return false;
        }

        // 2. [ERP-FAST] Sync o Creazione
        $roster = $existingRoster ?? PlayerSeasonRoster::firstOrNew([
            'player_id' => $player->id,
            'season_id' => $season->id,
            'team_id'   => $team->id,
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
            if ($merged !== $currentPos) $roster->detailed_position = $merged;
        }

        $roster->fill([
            'team_id' => $team->id,
            'role'    => $roster->role ?? $playerMainRole,
        ]);

        $wasDirty = $roster->isDirty();
        $roster->save();

        return $wasDirty;
    }

    private function createNewPlayer(array $playerData, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        // Metodo mantenuto per compatibilità, ma non più invocato dal sync API (Match o Nulla).
    }
}
