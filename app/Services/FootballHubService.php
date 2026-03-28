<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FootballHubService
{
    protected FootballDataApiService $apiService;
    protected FBrefScraperService $scraperService;

    public function __construct(
        FootballDataApiService $apiService,
        FBrefScraperService $scraperService
    ) {
        $this->apiService = $apiService;
        $this->scraperService = $scraperService;
    }

    /**
     * Master Reset: Truncate tables.
     */
    public function resetAll(): void
    {
        Log::warning("FootballHub: Avvio Reset Strutturale...");
        
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('teams')->truncate();
        DB::table('team_historical_standings')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        Log::info("FootballHub: Reset completato.");
    }

    /**
     * Master Sync: API + Scraper lookback.
     */
    public function synchronize(): void
    {
        Log::info("FootballHub: Inizio Sincronizzazione Ibrida v7.0");

        // STEP 1: API Sync (Serie A 23-25)
        $this->apiService->syncTeams('SA', [2023, 2024, 2025]);

        // STEP 2: Scraper Sync
        $seasons = ['2021-2022', '2022-2023', '2023-2024', '2024-2025'];
        
        foreach ($seasons as $season) {
            // Serie A (11)
            $this->scraperService->syncFbrefTeams(11, $season);
            
            // Serie B (18)
            $this->scraperService->syncFbrefTeams(18, $season);
        }

        Log::info("FootballHub: Sincronizzazione completata con successo.");
    }
}
