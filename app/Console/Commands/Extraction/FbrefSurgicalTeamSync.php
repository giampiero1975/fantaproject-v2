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
        $this->info("Stagione: " . SeasonHelper::formatYear($seasonYear));
        $this->info("Target: " . ($isAll ? "Intera Stagione (" . $teams->count() . " team)" : "Team: {$teams->first()->name}"));

        // Preparazione URL e Mappa Context per rintracciare i team post-scraping
        $urlMap = [];
        foreach ($teams as $team) {
            $url = $this->buildSurgicalUrl($team, $seasonYear);
            if ($url) {
                $urlMap[$url] = $team;
            }
        }

        if (empty($urlMap)) {
            $this->error("Nessun URL FBref valido rintracciabile per i target selezionati.");
            return Command::FAILURE;
        }

        $this->info("🌐 Inizio Scraping via dispatchScrape (" . count($urlMap) . " URL)...");

        try {
            // Delega Totale al Service (Gestione Proxy/Pipeline automatica)
            // Lo Scoping è 'surgical_sync' per ID/URL
            $response = $this->scrapingService->dispatchScrape(array_keys($urlMap), [
                'season_id' => $season->id,
                'season_year' => $seasonYear,
                'dry_run' => $this->option('dry-run'),
                'is_surgical' => true // Forza la logica di solo mapping
            ]);

            // Se la risposta è un oggetto (ScrapingJob), siamo in modalità Pipeline
            if ($response instanceof \App\Models\ScrapingJob) {
                $this->warn("⚙️ Richiesta massiva avviata in modalità PIPELINE (Job ID: {$response->job_id})");
                $this->info("I dati verranno elaborati in background via Webhook.");
                return Command::SUCCESS;
            }

            // Modalità DIRETTA (Sincrona: risposta success/failed per URL)
            $this->info("✅ Scraping Diretto completato. Elaborazione risultati...");

            foreach ($response as $url => $status) {
                $team = $urlMap[$url];
                if ($status === 'success') {
                    $this->info("✔️ Sync completato con successo per: {$team->name}");
                } else {
                    $this->error("❌ Sync fallito per: {$team->name} (URL: {$url})");
                }
            }

            $this->info("🏁 Operazione conclusa.");

        } catch (\Exception $e) {
            $this->error("ERRORE CRITICO: " . $e->getMessage());
            return Command::FAILURE;
        }

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
        $baseUrl = $team->fbref_url;
        
        if (!$baseUrl || !preg_match('/squads\/([a-f0-9]+)\//', $baseUrl, $matches)) {
            return null;
        }
        $fbrefId = $matches[1];

        $parts = explode('/', rtrim($baseUrl, '/'));
        $teamSlug = end($parts);
        if (!str_contains($teamSlug, '-Stats')) {
             $teamSlug = \Illuminate\Support\Str::slug($team->name) . "-Stats";
        }

        // Se è la stagione in corso (2025), usiamo l'URL principale senza l'anno nel path
        if ($year === (int) SeasonHelper::getCurrentSeason()) {
            return "https://fbref.com/en/squads/{$fbrefId}/{$teamSlug}";
        }

        $seasonPart = "{$year}-" . ($year + 1);
        return "https://fbref.com/en/squads/{$fbrefId}/{$seasonPart}/{$teamSlug}";
    }
}

