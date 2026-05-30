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
    /**
     * @var string
     */
    protected $signature = 'fbref:surgical-team-sync 
                            {team_id? : ID della squadra nel database} 
                            {--season= : Anno d\'inizio della stagione (es. 2024)}
                            {--all : Sincronizza tutti i team della stagione}
                            {--dry-run : Esegue il sync in modalità simulazione (nessuna scrittura)}';

    /**
     * @var string
     */
    protected $description = 'Sincronizzazione chirurgica dei profili FBref (ID e URL) per una squadra o l\'intera stagione.';

    /**
     * @var FbrefScrapingService
     */
    protected FbrefScrapingService $scrapingService;

    /**
     * @param FbrefScrapingService $scrapingService
     */
    public function __construct(FbrefScrapingService $scrapingService)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
    }

    /**
     * Esegue il comando di sincronizzazione.
     * 
     * @return int
     */
    public function handle(): int
    {
        $teamId = $this->argument('team_id');
        $seasonOption = $this->option('season');
        $seasonYear = (int) ($seasonOption ?: SeasonHelper::getCurrentSeason());
        $isAll = $this->option('all');

        // [PROTOCOLLO SICUREZZA] Validazione Bloccante: Target obbligatorio
        if (!$teamId && !$isAll) {
            $this->error("ERRORE: Devi fornire un 'team_id' o usare l'opzione '--all' per procedere.");
            return Command::FAILURE;
        }

        $season = Season::where('season_year', $seasonYear)->first();
        if (!$season) {
            $this->error("ERRORE: Stagione {$seasonYear} non trovata a DB.");
            return Command::FAILURE;
        }

        /** @var \Illuminate\Support\Collection $teams */
        $teams = collect();

        if ($isAll) {
            $this->info("🔍 Recupero tutti i team per la stagione " . SeasonHelper::formatYear($seasonYear));
            // Recupero tutti i team che hanno un roster nella stagione selezionata
            $teams = Team::whereHas('rosters', function ($q) use ($season) {
                $q->where('season_id', $season->id);
            })->get();
        } else {
            $team = Team::find($teamId);
            if (!$team) {
                $this->error("Squadra con ID {$teamId} non trovata.");
                return Command::FAILURE;
            }
            $teams->push($team);
        }

        $this->info("🚀 AVVIO SESSIONE SYNC CHIRURGICO FBREF (Rose)");
        $this->info("Stagione: " . \App\Helpers\SeasonHelper::formatYear($seasonYear));
        $this->info("Target: " . ($isAll ? "Intera Stagione (" . $teams->count() . " team)" : "Team: {$teams->first()->name}"));

        // Inizializzazione Log Persistente (Log Specifico in UI)
        $importLog = \App\Models\ImportLog::create([
            'import_type' => 'fbref_surgical_sync',
            'original_file_name' => "Surgical Sync: " . ($isAll ? "Multi-Team" : $teams->first()->name),
            'season_id' => $season->id,
            'status' => 'avviato',
            'details' => "Sync chirurgico avviato per " . ($isAll ? "{$teams->count()} team" : $teams->first()->name) . ".",
            'rows_processed' => 0,
            'rows_updated' => 0,
        ]);

        // Inizializzazione Proxy Manager
        $proxyManager = app(\App\Services\ProxyManagerService::class);

        $totalProcessed = 0;
        $totalUpdated = 0;

        foreach ($teams as $team) {
            $this->warn("\n------------------------------------------------");
            $this->info("⚽️ SQUADRA: {$team->name}");

            // Gap Analysis
            $rosterCount = PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $season->id)->count();
            $missingCount = PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $season->id)
                ->whereHas('player', fn($q) => $q->withTrashed()->whereNull('fbref_id'))
                ->count();

            $this->comment("📊 Gap rilevato: {$missingCount}/{$rosterCount} calciatori senza fbref_id.");

            if ($missingCount === 0) {
                $this->info("✅ Nessun gap rilevato. La squadra è già mappata al 100%. Salto (Risparmio Credito) 💸");
                \Illuminate\Support\Facades\Log::channel('fbref_surgical')->info("[FbrefSurgicalTeamSync] {$team->name}: Saltata. Gap 0 (Copertura 100%). Nessun proxy consumato.");
                continue;
            }

            $url = $this->buildSurgicalUrl($team, $seasonYear);
            if (!$url) {
                $this->error("❌ Impossibile generare URL per {$team->name}");
                \Illuminate\Support\Facades\Log::error("[FbrefSurgicalTeamSync] {$team->name}: Impossibile generare URL.");
                continue;
            }

            $this->line("🌐 Scraping: {$url}");
            \Illuminate\Support\Facades\Log::channel('fbref_surgical')->info("[FbrefSurgicalTeamSync] {$team->name}: Avvio scraping ({$missingCount} mancanti su {$rosterCount}).");
            
            $maxRetries = 3;
            $attempt = 0;
            $success = false;
            $playersData = [];

            while ($attempt < $maxRetries && !$success) {
                $attempt++;
                
                $proxy = $proxyManager->getBestProxy();
                if (!$proxy) {
                    $this->error("❌ Nessun proxy disponibile.");
                    break;
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
                    $status = 500; // Valore di default
                    if (preg_match('/Status:\s*(\d{3})/', $e->getMessage(), $matches)) {
                        $status = (int)$matches[1];
                    }
                    
                    if ($status == 403 || $status == 429 || str_contains($e->getMessage(), 'Account out of credits')) {
                        $this->error("🛑 Proxy '{$proxy->name}' ESAUSTO (Status {$status}). Switch a priority successiva...");
                        $proxyManager->markAsUnreliable($proxy, "Credit Limit Reached ({$status})");
                        $attempt--; 
                    } else if ($status == 500 || $status == 503) {
                        $this->error("⚠️ Proxy '{$proxy->name}' Errore Temporaneo 500 (Tentativo {$attempt}/{$maxRetries})...");
                        if ($attempt >= $maxRetries) {
                            $proxyManager->markAsUnreliable($proxy, "Persistent 500 error after {$maxRetries} tries");
                        } else {
                            sleep(2);
                        }
                    } else {
                        $this->error("❌ Errore inaspettato: " . $e->getMessage());
                        break;
                    }
                }
            }

            if (!$success) {
                $this->error("❌ Sync fallito per {$team->name} dopo {$maxRetries} tentativi.");
                continue;
            }

            // Sync con Rigid Matching
            $this->info("✅ Dati ricevuti. Avvio matching chirurgico...");
            
            $syncResults = $this->scrapingService->syncPlayersData(
                $playersData, 
                $team->id, 
                $season->id, 
                $this->option('dry-run'),
                true
            );

            $this->line("✨ Match: {$syncResults['matched']} | Aggiornati: {$syncResults['updated']} | Noise (Ignorati): {$syncResults['noise']}");
            \Illuminate\Support\Facades\Log::channel('fbref_surgical')->info("[FbrefSurgicalTeamSync] {$team->name}: Completato. Match: {$syncResults['matched']} | Aggiornati: {$syncResults['updated']} | Noise: {$syncResults['noise']}");
            
            if (!empty($syncResults['log'])) {
                foreach ($syncResults['log'] as $logLine) {
                    $this->line("   " . $logLine);
                    \Illuminate\Support\Facades\Log::channel('fbref_surgical')->info("   " . $logLine);
                }
            }

            $totalProcessed += $syncResults['total'] ?? 0;
            $totalUpdated += $syncResults['updated'] ?? 0;
        }

        $this->line("");
        $this->info("🏁 Operazione conclusa. Processati: $totalProcessed, Aggiornati: $totalUpdated");

        // Aggiorna il Log Persistente
        $importLog->update([
            'status' => $this->option('dry-run') ? 'simulato' : 'successo',
            'rows_processed' => $totalProcessed,
            'rows_updated' => $totalUpdated,
            'details' => "Elaborati $totalProcessed, aggiornati $totalUpdated calciatori." . ($this->option('dry-run') ? ' [MODALITÀ DRY-RUN]' : ''),
        ]);
        
        return Command::SUCCESS;
    }

    /**
     * Ricostruisce l'URL FBref preservando la logica stabile del commit 012071b.
     * 
     * @param Team $team
     * @param int $year
     * @return string|null
     */
    protected function buildSurgicalUrl(Team $team, int $year): ?string
    {
        return \App\Helpers\FbrefUrlHelper::getTeamUrl($team->fbref_id, $team->fbref_slug, $year);
    }
}

