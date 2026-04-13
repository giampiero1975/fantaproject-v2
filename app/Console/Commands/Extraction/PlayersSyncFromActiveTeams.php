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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'players:sync-from-active-teams
                            {--season=      : Stagione YYYY da processare (es. 2025). Default: current}
                            {--threshold=85 : Soglia di similitudine per il matching (default 85)}
                            {--force        : Riprocessa anche i calciatori già matchati}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizzazione Bottom-Up delle rose Serie A (API -> DB). Arricchisce i match e inserisce i nuovi (L4).';

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
        // ── Setup Logging ────────────────────────────────────────────────────
        $logDir  = storage_path('logs/Roster');
        $logPath = $logDir . '/RosterSync.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        $this->logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->logger->info('🔄 AVVIO SINCRONIZZAZIONE BOTTOM-UP (Step 7)');
        $this->logger->info('🕐 Ora: ' . now()->format('Y-m-d H:i:s'));

        // ── Determinazione Stagioni ──────────────────────────────────────────
        $requestedSeason = $this->option('season');
        $seasonModel = null;

        if ($requestedSeason) {
            $seasonModel = Season::where('season_year', $requestedSeason)->first();
        } else {
            $seasonModel = Season::where('is_current', true)->first();
        }

        if (!$seasonModel) {
            $this->error("Stagione non trovata o non configurata.");
            return Command::FAILURE;
        }

        $threshold = (float) $this->option('threshold');
        $forceMode = $this->option('force');

        $this->syncSeason($seasonModel, $threshold, $forceMode);

        return Command::SUCCESS;
    }

    private function syncSeason(Season $seasonModel, float $threshold, bool $forceMode)
    {
        $season = $seasonModel->season_year;
        $this->info("▶️ Elaborazione Stagione: {$season}");
        $this->logger->info("📅 Stagione: {$season}" . ($forceMode ? ' [FORCE MODE]' : ''));

        // ── Apertura ImportLog ───────────────────────────────────────────────
        $importLog = ImportLog::create([
            'season_id'          => $seasonModel->id,
            'import_type'        => 'sync_rose_api',
            'original_file_name' => "sync_rose_bottom_up_{$season}",
            'status'             => 'in_corso',
            'details'            => "In corso. Stagione: {$season}. Bottom-Up Threshold={$threshold}",
        ]);

        // ── Recupero squadre della stagione ──────────────────────────────────
        $activeTeams = Team::whereNotNull('api_id')
            ->whereHas('teamSeasons', function($q) use ($seasonModel) {
                $q->where('season_id', $seasonModel->id)->where('is_active', true);
            })->get();

        if ($activeTeams->isEmpty()) {
            $this->logger->warning("Nessuna squadra attiva con api_id trovata per {$season}.");
            $importLog->update(['status' => 'fallito', 'details' => "Nessuna squadra trovata per {$season}."]);
            return;
        }

        $updatedCount   = 0;
        $createdCount   = 0;
        $processedCount = 0;
        $totalTeams     = $activeTeams->count();

        $this->getOutput()->progressStart($totalTeams);

        foreach ($activeTeams as $teamIndex => $team) {
            $percent = (int) round(($teamIndex / $totalTeams) * 100);
            Cache::put('sync_rose_progress', [
                'running' => true,
                'percent' => $percent,
                'label'   => "Sync {$season}: {$team->short_name} ({$teamIndex}/{$totalTeams})",
                'team'    => $team->short_name,
                'done'    => false,
                'log_id'  => $importLog->id,
            ], 600);

            // 1. FETCH: Rosa ufficiale da API
            $squadFromApi = $this->teamDataService->getSquad($team->api_id);
            if (empty($squadFromApi)) {
                $this->logger->warning("Nessuna rosa API per {$team->name} (api_id={$team->api_id})");
                $this->getOutput()->progressAdvance();
                continue;
            }

            // 2. RECUPERO ROSTER LOCALE (Listone)
            $localRoster = PlayerSeasonRoster::with('player')
                ->where('team_id', $team->id)
                ->where('season_id', $seasonModel->id)
                ->get();

            foreach ($squadFromApi as $playerData) {
                if (empty($playerData['id']) || empty($playerData['name'])) continue;

                $processedCount++;
                $apiName = $playerData['name'];

                // 3. MATCH (Bottom-Up circoscritto a Squadra/Stagione)
                $match = $this->findLocalMatch($apiName, $localRoster, $threshold);

                $rawApiRole      = ['position' => $playerData['position'] ?? null];
                $normalizedRoles = $this->roleNormalizer->normalize($rawApiRole, 'football_data_api');
                $playerMainRole  = $normalizedRoles['role_main'];
                $apiDetailedPos  = $normalizedRoles['detailed_position'];

                $apiId   = $playerData['id'];
                $apiName = $playerData['name'];

                // 1. PRIORITÀ ASSOLUTA: ID API Globale (Registry Truth)
                $player = Player::withTrashed()->where('api_football_data_id', $apiId)->first();
                
                if ($player) {
                    if ($player->trashed()) {
                        $this->logger->info("♻️ RESTORE: '{$apiName}' (ID: {$apiId}) era cancellato. Ripristino.");
                        $player->restore();
                    }
                    $this->updateRegistry($player, $playerData, $apiDetailedPos);
                    $this->updateRosterForNewTeam($player, $seasonModel, $team, $playerMainRole, $apiDetailedPos);
                    $updatedCount++;
                } else {
                    // 2. PRIORITÀ SECONDARIA: Match Locale per Nome (Rescue from Listone)
                    $match = $this->findLocalMatch($apiName, $localRoster, $threshold);
                    
                    if ($match) {
                        $player = Player::find($match['player_id']);
                        $this->logger->info("🤝 MATCH LOCALE: '{$apiName}' associato a giocatore locale '{$match['local_name']}' (ID: {$player->id})");
                        $this->updateRegistry($player, $playerData, $apiDetailedPos);
                        $this->updateRoster($player, $seasonModel, $team, $playerMainRole, $apiDetailedPos);
                        $updatedCount++;
                    } else {
                        // 3. INSERT (NEW L4): Nessuna traccia globale o locale
                        $this->createNewPlayer($playerData, $seasonModel, $team, $playerMainRole, $apiDetailedPos);
                        $createdCount++;
                    }
                }
            }

            $this->getOutput()->progressAdvance();
            // Rate limit delay
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
            'details'        => "Completato Bottom-Up {$season}. Match={$updatedCount}, New={$createdCount}.",
        ]);

        $this->info("✅ Stagione {$season} completata. Match={$updatedCount}, New={$createdCount}.");
        $this->logger->info("🏁 FINE: Match={$updatedCount}, New={$createdCount}.");
    }

    private function findLocalMatch(string $apiName, $localRoster, float $threshold): ?array
    {
        // Prima cerchiamo match esatto per ID API se già presente nel Roster
        // (Copertura per reload o ri-esecuzioni)
        // [Omesso qui per attenersi strettamente al match testato nel Dry Run, ma implementabile]

        $bestMatch = null;
        $maxPct    = 0;

        foreach ($localRoster as $item) {
            if (!$item->player) continue;

            $dbName = $item->player->name;
            $pct = $this->calculateSimilarity($dbName, $apiName);

            if ($pct >= $threshold && $pct > $maxPct) {
                $maxPct = $pct;
                $bestMatch = [
                    'local_name' => $dbName,
                    'player_id'  => $item->player_id,
                    'pct'        => round($pct, 1),
                ];
            }
        }

        return $bestMatch;
    }

    private function updateRegistry(Player $player, array $playerData, ?array $apiDetailedPos)
    {
        $attrs = [
            'api_football_data_id' => $playerData['id'],
            'date_of_birth'        => isset($playerData['dateOfBirth']) 
                                      ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d') 
                                      : $player->date_of_birth,
        ];

        // FBref ID extraction
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

        $player->update($attrs);
    }

    private function updateRosterForNewTeam(Player $player, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        $roster = PlayerSeasonRoster::firstOrNew([
            'player_id' => $player->id,
            'season_id' => $season->id,
        ]);

        if (!$roster->team_id) {
            $roster->team_id = $team->id;
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

    private function updateRoster(Player $player, Season $season, Team $team, ?string $playerMainRole, ?array $apiDetailedPos)
    {
        $roster = PlayerSeasonRoster::where('player_id', $player->id)
            ->where('season_id', $season->id)
            ->first();

        if (!$roster) return;

        // Detailed position stagionale
        $currentPos = $roster->detailed_position ?? [];
        if (!empty($apiDetailedPos)) {
            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
            sort($merged);
            $roster->detailed_position = $merged;
        }
        
        // Se non abbiamo un ruolo fanta e l'API ci dà un ruolo, lo prendiamo come base
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
            'initial_quotation' => 0,
            'fanta_quotation'   => 0,
            'fvm'               => 0,
        ]);
        
        $this->logger->info("✅ CREATO (L4): '{$player->name}' | season={$season->season_year} | team={$team->name}");
    }

    private function calculateSimilarity(string $dbName, string $apiName): float
    {
        $dbTokens  = $this->getNormalizedTokens($dbName);
        $apiTokens = $this->getNormalizedTokens($apiName);
        if (empty($dbTokens) || empty($apiTokens)) return 0;

        $matches = 0;
        $shortSet = count($dbTokens) <= count($apiTokens) ? $dbTokens : $apiTokens;
        $longCopy = $shortSet === $dbTokens ? $apiTokens : $dbTokens;
        $total    = count($shortSet);

        foreach ($shortSet as $token) {
            reset($longCopy);
            $found = false;
            foreach ($longCopy as $k => $candidate) {
                if ($token === $candidate || (Str::endsWith($token, '.') && str_starts_with($candidate, rtrim($token, '.')))) {
                    $matches++;
                    unset($longCopy[$k]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $bestFuzzy = 0;
                $bestK = -1;
                foreach ($longCopy as $k => $candidate) {
                    similar_text($token, $candidate, $pct);
                    if ($pct > 80 && $pct > $bestFuzzy) {
                        $bestFuzzy = $pct;
                        $bestK = $k;
                    }
                }
                if ($bestK !== -1) {
                    $matches += ($bestFuzzy / 100);
                    unset($longCopy[$bestK]);
                }
            }
        }
        return ($matches / $total) * 100;
    }

    private function getNormalizedTokens(string $name): array
    {
        $n = Str::ascii(strtolower(trim($name)));
        $n = str_replace(["'", '-'], ' ', $n);
        $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return array_values(array_filter(explode(' ', trim($n))));
    }
}
