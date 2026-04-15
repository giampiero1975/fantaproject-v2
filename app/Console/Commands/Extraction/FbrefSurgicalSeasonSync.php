<?php

namespace App\Console\Commands\Extraction;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Models\Player;
use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Services\FbrefScrapingService;
use App\Services\ProxyManagerService;
use App\Helpers\SeasonHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FbrefSurgicalSeasonSync extends Command
{
    protected $signature = 'fbref:surgical-season-sync 
                            {season_id : ID della stagione nel database} 
                            {--dry-run : Esegue il sync in modalità simulazione (nessuna scrittura)}
                            {--force : Forza lo scraping anche per squadre senza gap}';

    protected $description = 'Sincronizzazione massiva chirurgica per un intera stagione di Serie A (League ID 1).';

    protected $scrapingService;
    protected $proxyManager;

    public function __construct(FbrefScrapingService $scrapingService, ProxyManagerService $proxyManager)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
        $this->proxyManager = $proxyManager;
    }

    public function handle()
    {
        $seasonId = $this->argument('season_id');
        $season = Season::find($seasonId);

        if (!$season) {
            $this->error("Stagione con ID {$seasonId} non trovata.");
            return Command::FAILURE;
        }

        // Inizializzazione Log Persistente della Sessione
        $importLog = \App\Models\ImportLog::create([
            'import_type' => 'fbref_surgical_sync',
            'original_file_name' => "Surgical Sync: Intera Serie A",
            'season_id' => $season->id,
            'status' => 'avviato',
            'details' => "Sync massivo stagionale avviato per l'intera Serie A.",
            'rows_processed' => 0,
            'rows_updated' => 0,
        ]);

        $this->info("🚀 AVVIO SYNC MASSIVO STAGIONALE: " . SeasonHelper::formatYear($season->season_year));
        
        // 1. Recupero Teams Serie A (League ID 1) per la stagione
        $teams = Team::whereHas('teamSeasons', function($q) use ($seasonId) {
            $q->where('season_id', $seasonId)->where('league_id', 1);
        })->whereNotNull('fbref_url')->get();

        if ($teams->isEmpty()) {
            $msg = "Nessuna squadra con fbref_url trovata per questa stagione.";
            $this->warn($msg);
            $importLog->update(['status' => 'successo', 'details' => $msg]);
            return Command::SUCCESS;
        }

        $this->info("🔍 Trovate " . $teams->count() . " squadre da analizzare.");

        $totalProcessed = 0;
        $totalUpdated = 0;

        foreach ($teams as $team) {
            $results = $this->processTeam($team, $season);
            if ($results) {
                $totalProcessed += $results['total'] ?? 0;
                $totalUpdated += $results['updated'] ?? 0;
            }
        }

        $summary = "🏁 Operazione conclusa per tutta la stagione. Processati: {$totalProcessed}, Aggiornati: {$totalUpdated}";
        $this->info("\n" . $summary);

        $importLog->update([
            'status' => $this->option('dry-run') ? 'simulato' : 'successo',
            'rows_processed' => $totalProcessed,
            'rows_updated' => $totalUpdated,
            'details' => $summary . ($this->option('dry-run') ? ' [MODALITÀ DRY-RUN]' : ''),
        ]);

        return Command::SUCCESS;
    }

    protected function processTeam(Team $team, Season $season): ?array
    {
        $this->warn("\n------------------------------------------------");
        $this->info("⚽️ SQUADRA: {$team->name}");

        // 2. Gap Analysis
        $rosterCount = PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $season->id)->count();
        $missingCount = PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $season->id)
            ->whereHas('player', fn($q) => $q->whereNull('fbref_id'))
            ->count();

        if ($missingCount === 0 && !$this->option('force')) {
            $this->line("✅ Nessun gap rilevato (Copertura 100%). Salto squadra.");
            return null;
        }

        $this->comment("📊 Gap rilevato: {$missingCount}/{$rosterCount} calciatori senza fbref_id.");

        // 3. Costruzione URL e Scraping con Failover
        $url = $this->buildSurgicalUrl($team, $season->season_year);
        if (!$url) {
            $this->error("❌ Impossibile generare URL per {$team->name}");
            return null;
        }

        $this->line("🌐 Scraping: {$url}");
        
        $maxRetries = 3;
        $attempt = 0;
        $success = false;
        $playersData = [];

        while ($attempt < $maxRetries && !$success) {
            $attempt++;
            
            $proxy = $this->proxyManager->getBestProxy();
            if (!$proxy) {
                $this->error("❌ Nessun proxy disponibile.");
                return null;
            }

            try {
                $this->scrapingService->setTargetUrl($url);
                $scrapedData = $this->scrapingService->scrapeTeamStats();
                
                if (isset($scrapedData['error'])) {
                    throw new \Exception($scrapedData['error']);
                }

                $playersData = $scrapedData['stats_standard'] ?? [];
                if (empty($playersData)) {
                    throw new \Exception("Tabella stats_standard non trovata.");
                }

                $success = true;
            } catch (\Exception $e) {
                $status = $this->extractStatusCode($e->getMessage());
                
                if ($status == 403 || $status == 429 || str_contains($e->getMessage(), 'Account out of credits')) {
                    $this->error("🛑 Proxy '{$proxy->name}' ESAUSTO (Status {$status}). Switch a priority successiva...");
                    $this->proxyManager->markAsUnreliable($proxy, "Credit Limit Reached ({$status})");
                    // In questo caso NON incrementiamo attempt, vogliamo cambiare proxy e riprovare lo stesso tentativo
                    $attempt--; 
                } else if ($status == 500 || $status == 503) {
                    $this->error("⚠️ Proxy '{$proxy->name}' Errore Temporaneo 500 (Tentativo {$attempt}/{$maxRetries})...");
                    if ($attempt >= $maxRetries) {
                        $this->proxyManager->markAsUnreliable($proxy, "Persistent 500 error after {$maxRetries} tries");
                    } else {
                        sleep(2); // Pausa prima del retry con lo stesso proxy
                    }
                } else {
                    $this->error("❌ Errore inaspettato: " . $e->getMessage());
                    break;
                }
            }
        }

        if (!$success) {
            $this->error("❌ Sync fallito per {$team->name} dopo {$maxRetries} tentativi.");
            return null;
        }

        // 4. Sync con Rigid Matching (Single Source of Truth)
        $this->info("✅ Dati ricevuti. Avvio matching chirurgico...");
        
        $syncResults = $this->scrapingService->syncPlayersData(
            $playersData, 
            $team->id, 
            $season->id, 
            $this->option('dry-run'),
            true // Rigid mapping active
        );

        $this->line("✨ Match: {$syncResults['matched']} | Aggiornati: {$syncResults['updated']} | Noise (Ignorati): {$syncResults['noise']}");

        return $syncResults;
    }

    protected function buildSurgicalUrl(Team $team, int $year): ?string
    {
        $baseUrl = $team->fbref_url;
        if (!preg_match('/squads\/([a-f0-9]+)\//', $baseUrl, $matches)) return null;
        $fbrefId = $matches[1];

        $parts = explode('/', rtrim($baseUrl, '/'));
        $teamSlug = end($parts);
        if (!str_contains($teamSlug, '-Stats')) {
             $teamSlug = Str::slug($team->name) . "-Stats";
        }

        if ($year === SeasonHelper::getCurrentSeason()) {
            return "https://fbref.com/en/squads/{$fbrefId}/{$teamSlug}";
        }

        $seasonPart = "{$year}-" . ($year + 1);
        return "https://fbref.com/en/squads/{$fbrefId}/{$seasonPart}/{$teamSlug}";
    }

    protected function extractStatusCode(string $message): int
    {
        if (preg_match('/Status: (\d+)/', $message, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
