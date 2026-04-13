<?php

namespace App\Console\Commands\Extraction;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;
use App\Services\TeamDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PlayersSyncDryRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'players:sync-dry-run {team=Milan} {season=2025} {--threshold=85}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TEST (Dry Run): Verifica il matching dei calciatori tra API e DB (Bottom-Up) senza scrivere nulla.';

    protected TeamDataService $teamDataService;

    /**
     * Create a new command instance.
     */
    public function __construct(TeamDataService $teamDataService)
    {
        parent::__construct();
        $this->teamDataService = $teamDataService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $teamNameInput = $this->argument('team');
        $seasonYear    = $this->argument('season');
        $threshold     = (float) $this->option('threshold');

        // 1. Recupero Squadra e Stagione
        $team = Team::where('name', 'like', "%{$teamNameInput}%")
                    ->orWhere('short_name', 'like', "%{$teamNameInput}%")
                    ->first();
        
        $seasonModel = Season::where('season_year', $seasonYear)->first();

        if (!$team || !$seasonModel) {
            $this->error("❌ Configurazione non trovata: Squadra [{$teamNameInput}] o Stagione [{$seasonYear}]");
            return Command::FAILURE;
        }

        if (!$team->api_id) {
            $this->error("❌ La squadra '{$team->name}' non ha un api_id configurato per football-data.org.");
            return Command::FAILURE;
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🧪 AVVIO TEST DRY RUN — BOTTOM-UP SYNC");
        $this->info("📅 Stagione: {$seasonYear}");
        $this->info("⚽ Squadra:  {$team->name} (API ID: {$team->api_id})");
        $this->info("⚙️  Soglia:   {$threshold}%");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // 2. FETCH: Recupero rosa ufficiale dall'API
        $this->comment("📡 Recupero rosa ufficiale dall'API...");
        $squadFromApi = $this->teamDataService->getSquad($team->api_id);

        if (empty($squadFromApi)) {
            $this->error("⚠️ Nessun dato ricevuto dall'API. Controlla la connessione o l'API Key.");
            return Command::FAILURE;
        }

        // 3. RECUPERO ROSTER LOCALE (Listone)
        $this->comment("📦 Lettura Roster locale (player_season_roster)...");
        $localRoster = PlayerSeasonRoster::with('player')
            ->where('team_id', $team->id)
            ->where('season_id', $seasonModel->id)
            ->get();

        $this->info("💡 Giocatori caricati dal Listone per questo team: " . $localRoster->count());
        $this->info(str_repeat('─', 50));

        $results = [];
        foreach ($squadFromApi as $playerData) {
            $apiName = $playerData['name'];
            $apiPos  = $playerData['position'] ?? 'N/A';
            
            // 4. MATCH: Ricerca nel roster locale (Bottom-Up)
            $match = $this->findLocalMatch($apiName, $localRoster, $threshold);

            if ($match) {
                // Colore in base alla precisione del match
                $color = $match['pct'] >= 98 ? 'green' : 'cyan';
                $results[] = [
                    'api_name'   => $apiName,
                    'status'     => "<fg={$color}>MATCH</>",
                    'local_match'=> $match['local_name'],
                    'pct'        => $match['pct'] . '%',
                    'action'     => 'Update Registry (ID: ' . $match['player_id'] . ')'
                ];
            } else {
                $results[] = [
                    'api_name'   => $apiName,
                    'status'     => '<fg=yellow>NEW</>',
                    'local_match'=> '-',
                    'pct'        => '-',
                    'action'     => 'Insert as new Player (L4)'
                ];
            }
        }

        // 5. OUTPUT TABELLA
        $this->table(
            ['Nome API (Ufficiale)', 'Stato', 'Corrispondenza DB', '%', 'Azione Simulata'],
            $results
        );

        $this->warn("\n⚠️  TEST COMPLETATO: Nessuna modifica è stata apportata al database.");
        
        return Command::SUCCESS;
    }

    /**
     * Esegue il matching tra il nome API e il roster locale.
     */
    private function findLocalMatch(string $apiName, $localRoster, float $threshold): ?array
    {
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

    /**
     * Calcola la similitudine tra due nomi usando tokenizzazione e similar_text.
     */
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
                // Match esatto o parziale (es: "V. " con "Vincenzo")
                if ($token === $candidate || (Str::endsWith($token, '.') && str_starts_with($candidate, rtrim($token, '.')))) {
                    $matches++;
                    unset($longCopy[$k]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Fuzzy match per token residui
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

    /**
     * Normalizza la stringa per il matching.
     */
    private function getNormalizedTokens(string $name): array
    {
        $n = Str::ascii(strtolower(trim($name)));
        $n = str_replace(["'", '-'], ' ', $n);
        $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return array_values(array_filter(explode(' ', trim($n))));
    }
}
