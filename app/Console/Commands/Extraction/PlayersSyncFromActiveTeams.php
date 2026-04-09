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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlayersSyncFromActiveTeams extends Command
{
    protected $signature = 'players:sync-from-active-teams
                            {--season=      : Stagione YYYY da processare (es. 2025). Default: current + 2 precedenti}
                            {--player_name= : [DEBUG] Processa solo il giocatore con questo nome}
                            {--force        : Rielabora TUTTI i player, anche quelli già collegati all\'API}';

    protected $description = 'Sincronizza le rose Serie A arricchendo i player (Anagrafica + Roster) con api_id, parent_team e date_of_birth.';

    protected TeamDataService          $teamDataService;
    protected RoleNormalizationService $roleNormalizer;
    protected \Psr\Log\LoggerInterface $logger;

    public function __construct(TeamDataService $teamDataService)
    {
        parent::__construct();
        $this->teamDataService = $teamDataService;
        $this->roleNormalizer  = new RoleNormalizationService();
    }

    public function handle(): int
    {
        $logDir  = storage_path('logs/Roster');
        $logPath = $logDir . '/RosterSync.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        $this->logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->logger->info('🔄  AVVIO SINCRONIZZAZIONE ROSE API (Step 7)');
        $this->logger->info('🕐  Ora: ' . now()->format('Y-m-d H:i:s'));

        // ── Determinazione Stagioni ──────────────────────────────────────────
        $requestedSeason = $this->option('season');
        $seasonsToProcess = [];

        if ($requestedSeason) {
            $seasonModel = Season::where('season_year', $requestedSeason)->first();
            if (!$seasonModel) {
                $this->error("Stagione {$requestedSeason} non trovata a DB.");
                return Command::FAILURE;
            }
            $seasonsToProcess[] = $seasonModel;
        } else {
            // Default: Current + 2 precedenti
            $current = Season::where('is_current', true)->first();
            if ($current) {
                $seasonsToProcess = Season::where('season_year', '<=', $current->season_year)
                    ->orderBy('season_year', 'desc')
                    ->limit(3)
                    ->get();
            }
        }

        if (empty($seasonsToProcess)) {
            $this->error('Nessuna stagione trovata da processare.');
            return Command::FAILURE;
        }

        $forceMode          = $this->option('force');
        $specificPlayerName = $this->option('player_name');

        foreach ($seasonsToProcess as $seasonModel) {
            $this->syncSeason($seasonModel, $forceMode, $specificPlayerName);
        }

        return Command::SUCCESS;
    }

    private function syncSeason(Season $seasonModel, bool $forceMode, ?string $specificPlayerName)
    {
        $season = $seasonModel->season_year;
        $this->info("▶️  Elaborazione Stagione: {$season}");
        $this->logger->info("📅  Stagione: {$season}" . ($forceMode ? ' [FORCE MODE]' : ''));

        // ── Apertura ImportLog ───────────────────────────────────────────────
        $importLog = ImportLog::create([
            'season_id'          => $seasonModel->id,
            'import_type'        => 'sync_rose_api',
            'original_file_name' => "sync_rose_{$season}.api",
            'status'             => 'in_corso',
            'details'            => "In corso. Stagione: {$season}. Force=" . ($forceMode ? 'S' : 'N'),
        ]);

        // ── Recupero squadre della stagione ──────────────────────────────────
        // Cerchiamo le squadre che hanno un api_id e che sono attive in questa stagione
        $activeTeams = Team::whereNotNull('api_id')
            ->whereHas('teamSeasons', function($q) use ($seasonModel) {
                $q->where('season_id', $seasonModel->id)->where('is_active', true);
            })->get();

        if ($activeTeams->isEmpty()) {
            $this->logger->warning("Nessuna squadra attiva con api_id trovata per {$season}.");
            $importLog->update(['status' => 'fallito', 'details' => "Nessuna squadra trovata per {$season}."]);
            return;
        }

        // ── Contatori ────────────────────────────────────────────────────────
        $updatedCount   = 0;
        $createdCount   = 0;
        $processedCount = 0;
        $totalTeams     = $activeTeams->count();

        $this->getOutput()->progressStart($totalTeams);

        foreach ($activeTeams as $teamIndex => $team) {
            // ── Cache Progress (per UI Filament) ──────────────────────────────
            $percent = (int) round(($teamIndex / $totalTeams) * 100);
            Cache::put('sync_rose_progress', [
                'running' => true,
                'percent' => $percent,
                'label'   => "Sync {$season}: {$team->short_name} ({$teamIndex}/{$totalTeams})",
                'team'    => $team->short_name,
                'done'    => false,
                'log_id'  => $importLog->id,
            ], 600);

            $squadFromApi = $this->teamDataService->getSquad($team->api_id);
            if (empty($squadFromApi)) {
                $this->logger->warning("Nessuna rosa API per {$team->name} (api_id={$team->api_id})");
                $this->getOutput()->progressAdvance();
                continue;
            }

            foreach ($squadFromApi as $playerData) {
                if (empty($playerData['id']) || empty($playerData['name'])) continue;

                if ($specificPlayerName && stripos($this->getNormalizedStringForFilter($playerData['name']), $this->getNormalizedStringForFilter($specificPlayerName)) === false) {
                    continue;
                }

                $processedCount++;
                
                // 1. Matching del giocatore (Registry)
                $player = $this->findPlayer($playerData, $team, $forceMode);

                $rawApiRole      = ['position' => $playerData['position'] ?? null];
                $normalizedRoles = $this->roleNormalizer->normalize($rawApiRole, 'football_data_api');
                $playerMainRole  = $normalizedRoles['role_main'];
                $apiDetailedPos  = $normalizedRoles['detailed_position'];

                if ($player) {
                    // Update Registry (players)
                    $this->updateRegistry($player, $playerData, $team, $playerMainRole, $apiDetailedPos);
                    
                    // Update/Create Roster (player_season_roster)
                    $this->updateRoster($player, $seasonModel, $team, $playerMainRole, $apiDetailedPos);
                    
                    $updatedCount++;
                } else {
                    // L4: Creazione nuovo record (Registry + Roster)
                    $this->createNewPlayer($playerData, $seasonModel, $team, $playerMainRole, $apiDetailedPos);
                    $createdCount++;
                }
            }

            $this->getOutput()->progressAdvance();
            // Delay per rispettare rate limit (standard 10 richieste/min = 6s)
            if (($teamIndex + 1) < $totalTeams) {
                sleep(config('services.player_stats_api.providers.football_data_org.delay', 7));
            }
        }

        $this->getOutput()->progressFinish();

        $importLog->update([
            'status'         => 'successo',
            'rows_processed' => $processedCount,
            'rows_created'   => $createdCount,
            'rows_updated'   => $updatedCount,
            'details'        => "Completato {$season}. Ag={$updatedCount}, Cr={$createdCount}.",
        ]);

        $this->info("✅ Stagione {$season} completata. Ag={$updatedCount}, Cr={$createdCount}.");
    }

    /**
     * Aggiorna l'anagrafica (Registry)
     */
    private function updateRegistry(Player $player, array $playerData, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        $attrs = [
            'api_football_data_id' => $playerData['id'],
            'date_of_birth'        => isset($playerData['dateOfBirth']) 
                                      ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d') 
                                      : $player->date_of_birth,
        ];

        // FBref ID extraction (se presente nell'URL)
        if ($player->fbref_url && !$player->fbref_id) {
            if (preg_match('/players\/([a-f0-9]+)/', $player->fbref_url, $matches)) {
                $attrs['fbref_id'] = $matches[1];
            }
        }

        // Detailed Position Merge
        $currentPos = $player->detailed_position ?? [];
        if (!empty($apiDetailedPos)) {
            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
            sort($merged);
            if ($merged !== $currentPos) $attrs['detailed_position'] = $merged;
        }

        // Parent Team (Registry = Current Owner)
        // Se il giocatore è in una squadra diversa da quella del listone (Registry team_id), 
        // impostiamo questa come parent_team_id (Owner).
        // Nota: questo è un trigger empirico dalla V1.
        if ($player->getAttribute('team_id') && $player->getAttribute('team_id') != $team->id) {
            $attrs['parent_team_id'] = $team->id;
        }

        $player->update($attrs);
    }

    /**
     * Aggiorna o crea il record stagionale (Roster)
     */
    private function updateRoster(Player $player, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        $roster = PlayerSeasonRoster::firstOrNew([
            'player_id' => $player->id,
            'season_id' => $season->id,
        ]);

        // Se il roster non esiste (nuovo import listone), o se team_id è vuoto
        if (!$roster->team_id) {
            $roster->team_id = $team->id;
        }

        // Se il giocatore è trovato in una squadra diversa in questa stagione
        if ($roster->team_id != $team->id) {
            $roster->parent_team_id = $team->id;
        }

        // Detailed position stagionale
        $currentPos = $roster->detailed_position ?? [];
        if (!empty($apiDetailedPos)) {
            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
            sort($merged);
            $roster->detailed_position = $merged;
        }
        
        if ($playerMainRole && !$roster->role) {
            $roster->role = $playerMainRole;
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
        ]);
        
        $this->logger->info("✅ CREATO (L4): '{$player->name}' | season={$season->season_year}");
    }

    private function findPlayer(array $playerData, Team $team, bool $forceMode = false): ?Player
    {
        $player = Player::withTrashed()->where('api_football_data_id', $playerData['id'])->first();
        if ($player) return $player;

        $baseQuery = Player::withTrashed()->whereNotNull('fanta_platform_id');
        if (!$forceMode) $baseQuery = $baseQuery->whereNull('api_football_data_id');

        // L2: Match locale
        // Cerchiamo nel roster stagionale della squadra corrente
        $rosterIds = PlayerSeasonRoster::where('team_id', $team->id)->pluck('player_id');
        $localPlayers = (clone $baseQuery)->whereIn('id', $rosterIds)->get();
        foreach ($localPlayers as $local) {
            if ($this->namesAreSimilar($local->name, $playerData['name'])) return $local;
        }

        // L3: Match globale
        $all = $baseQuery->get();
        foreach ($all as $local) {
            if ($this->namesAreSimilar($local->name, $playerData['name'])) return $local;
        }

        return null;
    }

    private function namesAreSimilar(string $dbName, string $apiName): bool
    {
        $dbTokens  = $this->getNormalizedTokens($dbName);
        $apiTokens = $this->getNormalizedTokens($apiName);
        if (empty($dbTokens) || empty($apiTokens)) return false;

        $shortSet = count($dbTokens) <= count($apiTokens) ? $dbTokens : $apiTokens;
        $longCopy = $shortSet === $dbTokens ? $apiTokens : $dbTokens;

        foreach ($shortSet as $token) {
            $bestIdx = -1;
            foreach ($longCopy as $k => $candidate) {
                if ($token === $candidate || (Str::endsWith($token, '.') && str_starts_with($candidate, rtrim($token, '.')))) {
                    $bestIdx = $k; break;
                }
            }
            if ($bestIdx === -1) {
                $best = 0;
                foreach ($longCopy as $k => $candidate) {
                    similar_text($token, $candidate, $pct);
                    if ($pct > 85 && $pct > $best) { $best = $pct; $bestIdx = $k; }
                }
            }
            if ($bestIdx === -1) return false;
            unset($longCopy[$bestIdx]);
        }
        return true;
    }

    private function getNormalizedTokens(string $name): array
    {
        $n = Str::ascii(strtolower(trim($name)));
        $n = str_replace(["'", '-'], ' ', $n);
        $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return array_values(array_filter(explode(' ', trim($n))));
    }

    private function getNormalizedStringForFilter(string $name): string
    {
        return Str::slug($name, ' ');
    }
}

