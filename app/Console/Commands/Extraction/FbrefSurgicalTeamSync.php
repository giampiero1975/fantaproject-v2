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

class FbrefSurgicalTeamSync extends Command
{
    protected $signature = 'fbref:surgical-team-sync 
                            {team_id : ID della squadra nel database} 
                            {--season= : Anno d\'inizio della stagione (es. 2024)}
                            {--dry-run : Esegue il sync in modalità simulazione (nessuna scrittura)}';

    protected $description = 'Sincronizzazione chirurgica dei profili FBref (ID e URL) per una squadra e stagione specifica.';

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
        $teamId = $this->argument('team_id');
        $seasonYear = $this->option('season') ?: SeasonHelper::getCurrentSeason();

        $team = Team::find($teamId);
        if (!$team) {
            $this->error("Squadra con ID {$teamId} non trovata.");
            return Command::FAILURE;
        }

        $season = Season::where('season_year', $seasonYear)->first();
        if (!$season) {
            $this->error("Stagione {$seasonYear} non trovata a DB.");
            return Command::FAILURE;
        }

        // Inizializzazione Log Persistente
        $importLog = \App\Models\ImportLog::create([
            'import_type' => 'fbref_surgical_sync',
            'original_file_name' => "Surgical Sync: {$team->name}", // Salviamo il contesto qui per la tabella
            'season_id' => $season->id,
            'status' => 'avviato',
            'details' => "Sync chirurgico avviato per {$team->name}.",
            'rows_processed' => 0,
            'rows_updated' => 0,
        ]);

        $this->log("==================================================================", 'info');
        $this->log("🚀 AVVIO SESSIONE SYNC CHIRURGICO FBREF", 'info');
        $this->log("SQUADRA: {$team->name} (ID: {$team->id})", 'info');
        $this->log("STAGIONE: " . SeasonHelper::formatYear($seasonYear), 'info');
        
        if (!$team->fbref_url) {
            $msg = "ERRORE: La squadra '{$team->name}' non ha una URL FBref mappata.";
            $this->error($msg);
            $this->log($msg, 'error');
            $importLog->update(['status' => 'fallito', 'details' => $msg]);
            return Command::FAILURE;
        }

        // 1. Costruzione URL Chirurgico
        $url = $this->buildSurgicalUrl($team, $seasonYear);
        if (!$url) {
            $msg = "ERRORE: Impossibile costruire la URL per questa squadra.";
            $this->error($msg);
            $this->log($msg, 'error');
            $importLog->update(['status' => 'fallito', 'details' => $msg]);
            return Command::FAILURE;
        }

        $this->info("🌐 Target URL: {$url}");
        $this->log("Inizio scraping tabella standard... Target URL: {$url}", 'info');

        // 2. Scraping con Failover e Rotazione Proxy
        $maxRetries = 3;
        $attempt = 0;
        $success = false;
        $playersData = [];

        while ($attempt < $maxRetries && !$success) {
            $attempt++;
            
            $proxy = $this->proxyManager->getBestProxy();
            if (!$proxy) {
                $msg = "ERRORE: Nessun proxy disponibile per lo scraping.";
                $this->error($msg);
                $importLog->update(['status' => 'fallito', 'details' => $msg]);
                return Command::FAILURE;
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
                    $attempt--; // Non contiamo il tentativo se il proxy è esaurito
                } else if ($status == 500 || $status == 503) {
                    $this->error("⚠️ Proxy '{$proxy->name}' Errore Temporaneo 500 (Tentativo {$attempt}/{$maxRetries})...");
                    if ($attempt >= $maxRetries) {
                        $this->proxyManager->markAsUnreliable($proxy, "Persistent 500 error after {$maxRetries} tries");
                    } else {
                        sleep(2);
                    }
                } else {
                    $msg = "ERRORE INASPETTATO: " . $e->getMessage();
                    $this->error($msg);
                    $importLog->update(['status' => 'fallito', 'details' => $msg]);
                    return Command::FAILURE;
                }
            }
        }

        if (!$success) {
            $msg = "ERRORE: Sync fallito per {$team->name} dopo {$maxRetries} tentativi.";
            $this->error($msg);
            $importLog->update(['status' => 'fallito', 'details' => $msg]);
            return Command::FAILURE;
        }

        $this->info("📊 Trovati " . count($playersData) . " calciatori su FBref.");
        $this->log("Dati estratti con successo. Giocatori su FBref: " . count($playersData), 'info');
        $importLog->update(['rows_processed' => count($playersData)]);

        // 3. Sync e Matching Rigido (SST)
        $syncResults = $this->scrapingService->syncPlayersData(
            $playersData, 
            $team->id, 
            $season->id, 
            $this->option('dry-run'),
            true // Rigid mapping active
        );

        foreach ($syncResults['log'] as $logLine) {
            if (str_contains($logLine, '✅') || str_contains($logLine, '🧪')) {
                $this->line($logLine);
            } elseif (str_contains($logLine, '❓')) {
                $this->warn($logLine);
            } else {
                $this->info($logLine);
            }
            $this->log($logLine, str_contains($logLine, '❓') ? 'warning' : 'info');
        }

        $this->info("\n📊 RIEPILOGO SINCRONIZZAZIONE");
        $this->table(
            ['Categoria', 'Conteggio'],
            [
                ['Calciatori Scansionati (FBref)', $syncResults['total']],
                ['Match Trovati e Aggiornati', $syncResults['updated']],
                ['Già Mappati Correttamente', $syncResults['matched'] - $syncResults['updated']],
                ['Ignorati (Noise/Primavera)', $syncResults['noise']],
            ]
        );

        $summary = "🏁 FINE. Match: {$syncResults['matched']} | Aggiornati: {$syncResults['updated']} | Noise: {$syncResults['noise']}";
        $this->log($summary, 'info');
        $this->log("==================================================================\n", 'info');

        $importLog->update([
            'status' => $this->option('dry-run') ? 'simulato' : 'successo',
            'rows_updated' => $syncResults['updated'] ?? 0,
            'details' => $summary . ($this->option('dry-run') ? ' [MODALITÀ DRY-RUN]' : ''),
        ]);

        return Command::SUCCESS;
    }

    protected function log(string $message, string $level = 'info'): void
    {
        Log::channel('fbref_surgical')->$level($message);
    }

    protected function buildSurgicalUrl(Team $team, int $year): ?string
    {
        $baseUrl = $team->fbref_url;
        
        if (!preg_match('/squads\/([a-f0-9]+)\//', $baseUrl, $matches)) {
            return null;
        }
        $fbrefId = $matches[1];

        $parts = explode('/', rtrim($baseUrl, '/'));
        $teamSlug = end($parts);
        if (!str_contains($teamSlug, '-Stats')) {
             $teamSlug = Str::slug($team->name) . "-Stats";
        }

        // Se è la stagione in corso (2025), usiamo l'URL principale senza l'anno nel path
        if ($year === SeasonHelper::getCurrentSeason()) {
            return "https://fbref.com/en/squads/{$fbrefId}/{$teamSlug}";
        }

        $seasonPart = "{$year}-" . ($year + 1);
        return "https://fbref.com/en/squads/{$fbrefId}/{$seasonPart}/{$teamSlug}";
    }

    protected function normalize(string $name): string
    {
        $name = strtolower(Str::ascii($name));
        return preg_replace('/[^a-z]/', '', $name);
    }

    protected function extractStatusCode(string $message): int
    {
        if (preg_match('/Status: (\d+)/', $message, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
