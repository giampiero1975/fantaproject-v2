<?php

namespace App\Console\Commands\Extraction;

use App\Models\ImportLog;
use App\Models\Player;
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
                            {--player_name= : [DEBUG] Processa solo il giocatore con questo nome}
                            {--force        : Rielabora TUTTI i player, anche quelli già collegati all\'API}';

    protected $description = 'Sincronizza le rose Serie A arricchendo i player con api_football_data_id, '
                           . 'date_of_birth, detailed_position. [SAFE] Direttiva No-Delete: nessun record cancellato.';

    protected TeamDataService         $teamDataService;
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
        // ── Logger dedicato ──────────────────────────────────────────────────
        $logDir  = storage_path('logs/Roster');
        $logPath = $logDir . '/RosterSync.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        $this->logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->logger->info('🔄  AVVIO SINCRONIZZAZIONE ROSE API (Step 5)');
        $this->logger->info('🕐  Ora: ' . now()->format('Y-m-d H:i:s'));

        // ── Auto-season dal DB ───────────────────────────────────────────────
        $season = (string) DB::table('teams')
            ->where('serie_a_team', 1)
            ->whereNotNull('api_football_data_id')
            ->max('season_year');

        if (!$season) {
            $this->error('Impossibile rilevare season_year da DB. Verifica tabella teams.');
            return Command::FAILURE;
        }

        $forceMode          = $this->option('force');
        $specificPlayerName = $this->option('player_name');

        $this->logger->info("📅  Stagione auto-rilevata: {$season}" . ($forceMode ? ' [FORCE MODE]' : ''));
        $this->info("▶️  Stagione: {$season}" . ($forceMode ? ' [FORCE: rielabora tutto]' : ' [solo NULL api_id]'));

        if ($specificPlayerName) {
            $this->warn("⚠️  MODALITÀ DEBUG: solo '{$specificPlayerName}'.");
            $this->logger->warning("MODALITÀ DEBUG: filtro '{$specificPlayerName}'.");
        }

        // ── Apertura ImportLog ───────────────────────────────────────────────
        $importLog = ImportLog::create([
            'import_type'        => 'sync_rose_api',
            'original_file_name' => "sync_rose_{$season}" . ($forceMode ? '_force' : '') . ($specificPlayerName ? '_debug' : '') . ".api",
            'status'             => 'in_corso',
            'details'            => "Avvio. Stagione: {$season}." . ($forceMode ? ' [FORCE]' : ' [Solo NULL api_id]'),
        ]);
        $this->logger->info("📋  ImportLog ID: {$importLog->id} creato (status: in_corso)");

        // ── Recupero squadre ─────────────────────────────────────────────────
        $activeTeams = Team::where('serie_a_team', 1)
            ->where('season_year', $season)
            ->whereNotNull('api_football_data_id')
            ->get();

        if ($activeTeams->isEmpty()) {
            $activeTeams = Team::where('serie_a_team', 1)->whereNotNull('api_football_data_id')->get();
            $this->logger->warning("season_year={$season} vuoto: uso tutte le squadre serie_a=1.");
        }

        if ($activeTeams->isEmpty()) {
            $importLog->update(['status' => 'fallito', 'details' => 'Nessuna squadra trovata in DB.']);
            return Command::FAILURE;
        }

        $this->logger->info("📋  {$activeTeams->count()} squadre caricate.");
        $this->info("📋 {$activeTeams->count()} squadre.");

        // ── Cache: reset progress ────────────────────────────────────────────
        Cache::put('sync_rose_progress', [
            'running'  => true,
            'percent'  => 0,
            'label'    => 'Avvio...',
            'team'     => '',
            'done'     => false,
            'log_id'   => $importLog->id,
        ], 600);

        // ── Contatori ────────────────────────────────────────────────────────
        $updatedCount   = 0;
        $createdCount   = 0;
        $restoredCount  = 0;
        $processedCount = 0;
        $totalTeams     = $activeTeams->count();

        $this->getOutput()->progressStart($totalTeams);

        foreach ($activeTeams as $teamIndex => $team) {
            $percent = (int) round(($teamIndex / $totalTeams) * 100);

            // ── Avanzamento Cache (real-time) ─────────────────────────────────
            Cache::put('sync_rose_progress', [
                'running'  => true,
                'percent'  => $percent,
                'label'    => "Elaborazione {$team->short_name} ({$teamIndex}/{$totalTeams})...",
                'team'     => $team->short_name ?? $team->name,
                'done'     => false,
                'log_id'   => $importLog->id,
            ], 600);

            // ── Aggiornamento intermedio ImportLog ogni 5 squadre ────────────
            if ($teamIndex > 0 && $teamIndex % 5 === 0) {
                $importLog->update([
                    'rows_processed' => $processedCount,
                    'rows_created'   => $createdCount,
                    'rows_updated'   => $updatedCount,
                    'details'        => "In corso: {$teamIndex}/{$totalTeams} squadre. Ag={$updatedCount} Cr={$createdCount}.",
                ]);
            }

            $squadFromApi = $this->teamDataService->getSquad($team->api_football_data_id);
            if (empty($squadFromApi)) {
                $this->logger->warning("Nessuna rosa API: '{$team->name}' (api_id={$team->api_football_data_id}).");
                $this->getOutput()->progressAdvance();
                continue;
            }

            $squadCount = count($squadFromApi);
            $this->logger->info("Team '{$team->name}' → {$squadCount} giocatori API.");

            foreach ($squadFromApi as $playerData) {
                if (empty($playerData['id']) || empty($playerData['name'])) continue;

                // Filtro debug
                if ($specificPlayerName) {
                    if (stripos($this->getNormalizedStringForFilter($playerData['name']),
                                $this->getNormalizedStringForFilter($specificPlayerName)) === false) continue;
                }

                // ── EFFICIENZA: salta player già collegati (a meno di --force) ─
                if (!$forceMode) {
                    $alreadyLinked = Player::where('api_football_data_id', $playerData['id'])->exists();
                    if ($alreadyLinked) {
                        $this->logger->debug("SKIP (già collegato): api_id={$playerData['id']} '{$playerData['name']}'.");
                        continue;
                    }
                }

                $processedCount++;
                $this->logger->debug("→ '{$playerData['name']}' (api_id={$playerData['id']}) team='{$team->name}'");

                $rawApiRole      = ['position' => $playerData['position'] ?? null];
                $normalizedRoles = $this->roleNormalizer->normalize($rawApiRole, 'football_data_api');
                $playerMainRole  = $normalizedRoles['role_main'];
                $apiDetailedPos  = $normalizedRoles['detailed_position'];

                $player = $this->findPlayer($playerData, $team, $forceMode);

                if ($player) {
                    $wasTrashed = $player->trashed();
                    if ($wasTrashed) {
                        $player->restore();
                        $restoredCount++;
                        $this->logger->info("♻️  RIPRISTINATO: '{$player->name}' (ID DB: {$player->id}).");
                    }

                    $attrs = [
                        'api_football_data_id' => $playerData['id'],
                        'date_of_birth'        => isset($playerData['dateOfBirth'])
                            ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d')
                            : $player->date_of_birth,
                    ];

                    // Ruolo: listone è fonte di verità
                    if (empty($player->role) && $playerMainRole !== null) {
                        $attrs['role'] = $playerMainRole;
                    } elseif (!empty($player->role) && $playerMainRole && $player->role !== $playerMainRole) {
                        $this->logger->warning("Ruolo incongruo '{$player->name}': DB={$player->role} vs API={$playerMainRole}. Mantenuto DB.");
                    }

                    // detailed_position: merge non-distruttivo
                    $currentPos = $player->detailed_position ?? [];
                    if (!empty($apiDetailedPos)) {
                        if (!empty($currentPos)) {
                            $merged = array_values(array_unique(array_merge($currentPos, $apiDetailedPos)));
                            sort($merged);
                            if ($merged !== $currentPos) $attrs['detailed_position'] = $merged;
                        } else {
                            $attrs['detailed_position'] = $apiDetailedPos;
                        }
                    }

                    // Gestione prestiti
                    if ($player->team_id !== $team->id) {
                        $attrs['parent_team_id'] = $team->id;
                        $this->logger->info("🔄 Prestito: '{$player->name}' → parent_team_id={$team->id}.");
                    } else {
                        $attrs['team_id']        = $team->id;
                        $attrs['team_name']      = $team->short_name;
                        $attrs['parent_team_id'] = null;
                    }

                    $player->update($attrs);
                    if ($player->wasChanged()) {
                        $updatedCount++;
                        $this->logger->info("✅ AGGIORNATO: '{$player->name}' (ID DB: {$player->id}).");
                    } else {
                        $this->logger->debug("⭕ NESSUNA MODIFICA: '{$player->name}'.");
                    }

                } else {
                    // L4: nuovo da API
                    Player::create([
                        'api_football_data_id' => $playerData['id'],
                        'name'                 => $playerData['name'],
                        'date_of_birth'        => isset($playerData['dateOfBirth'])
                            ? Carbon::parse($playerData['dateOfBirth'])->format('Y-m-d')
                            : null,
                        'role'                 => $playerMainRole,
                        'detailed_position'    => $apiDetailedPos,
                        'team_id'              => $team->id,
                        'team_name'            => $team->short_name,
                    ]);
                    $createdCount++;
                    $this->logger->info("✅ CREATO (L4): '{$playerData['name']}' | team='{$team->name}'.");
                }
            }

            $this->getOutput()->progressAdvance();

            if (($teamIndex + 1) < $totalTeams) {
                sleep(config('services.player_stats_api.providers.football_data_org.delay', 7));
            }
        }

        $this->getOutput()->progressFinish();

        // ── Rilevamento orfani (No-Delete) ───────────────────────────────────
        $orphansList = [];
        if (!$specificPlayerName) {
            $unmatched = Player::whereNotNull('fanta_platform_id')
                ->whereNull('api_football_data_id')
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'team_name', 'fanta_platform_id', 'role']);

            foreach ($unmatched as $p) {
                $msg = "[{$p->id}] {$p->name} | {$p->team_name} | {$p->role}";
                $this->logger->warning("[NO-DELETE] Non matchato: {$msg}");
                $orphansList[] = $msg;
            }

            $orphanCount = count($orphansList);
            if ($orphanCount > 0) {
                $this->warn("⚠️  {$orphanCount} orfani non matchati (No-Delete).");
            } else {
                $this->info("✅ Tutti i player del listone matchati.");
                $this->logger->info("✅ Nessun orfano.");
            }
        }

        // ── Chiusura ImportLog ───────────────────────────────────────────────
        $orphanCount   = count($orphansList);
        $orphanSummary = $orphanCount > 0
            ? "Orfani: {$orphanCount}. Es.: " . implode(' | ', array_slice($orphansList, 0, 3)) . ($orphanCount > 3 ? '...' : '')
            : 'Nessun orfano.';

        $importLog->update([
            'status'         => 'successo',
            'rows_processed' => $processedCount,
            'rows_created'   => $createdCount,
            'rows_updated'   => $updatedCount,
            'details'        => "Stagione {$season}" . ($forceMode ? ' [FORCE]' : '') . ". Ag={$updatedCount}, Cr={$createdCount}, Rip={$restoredCount}. {$orphanSummary}",
        ]);

        $this->logger->info("📋  ImportLog #{$importLog->id} → successo");
        $this->logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->logger->info("📊  Ag={$updatedCount} | Cr={$createdCount} | Rip={$restoredCount} | Orfani={$orphanCount}");
        $this->logger->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // ── Cache: fine processo ─────────────────────────────────────────────
        Cache::put('sync_rose_progress', [
            'running'    => false,
            'percent'    => 100,
            'label'      => "Completato. Ag={$updatedCount} | Cr={$createdCount} | Orfani={$orphanCount}",
            'team'       => '',
            'done'       => true,
            'log_id'     => $importLog->id,
            'aggiornati' => $updatedCount,
            'creati'     => $createdCount,
            'orfani'     => $orphanCount,
        ], 300);

        // ── Output terminale ─────────────────────────────────────────────────
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("📊 Riepilogo Stagione {$season}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  ✅ Aggiornati   : {$updatedCount}");
        $this->info("  ✅ Creati (L4)  : {$createdCount}");
        $this->info("  ♻️  Ripristinati : {$restoredCount}");
        $this->info("  ⚠️  Orfani       : {$orphanCount}");
        $this->info("  📋 Log ID       : #{$importLog->id}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return Command::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════
    // MATCHING ENGINE — 4 Livelli
    // ════════════════════════════════════════════════════════════════════════

    /**
     * In modalità normale cerca solo tra player con api_football_data_id NULL.
     * In --force mode cerca tra tutti (withTrashed incluso).
     */
    private function findPlayer(array $playerData, Team $team, bool $forceMode = false): ?Player
    {
        // L1: ID API già in DB
        $player = Player::withTrashed()->where('api_football_data_id', $playerData['id'])->first();
        if ($player) {
            $this->logger->debug("L1 ✅ '{$player->name}' (DB id={$player->id}).");
            return $player;
        }

        // Base query: in normal mode solo player senza api_id collegato
        $baseQuery = Player::withTrashed()->whereNotNull('fanta_platform_id');
        if (!$forceMode) {
            $baseQuery = $baseQuery->whereNull('api_football_data_id');
        }

        // L2: Nome simile nello stesso team
        $inTeam = (clone $baseQuery)->where('team_id', $team->id)->get();
        foreach ($inTeam as $local) {
            if ($this->namesAreSimilar($local->name, $playerData['name'])) {
                $this->logger->debug("L2 ✅ '{$playerData['name']}' → '{$local->name}' (DB id={$local->id}).");
                return $local;
            }
        }

        // L3: Nome simile globale (trasferiti)
        $all = $baseQuery->get();
        foreach ($all as $local) {
            if ($this->namesAreSimilar($local->name, $playerData['name'])) {
                $this->logger->debug("L3 ✅ '{$playerData['name']}' → '{$local->name}' (era in {$local->team_name}).");
                return $local;
            }
        }

        $this->logger->debug("L4: '{$playerData['name']}' → nuovo record.");
        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ALGORITMO DI SIMILARITÀ — Erosione Ibrida
    // ════════════════════════════════════════════════════════════════════════

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
