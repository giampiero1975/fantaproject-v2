<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class FBrefScraperService
{
    protected ProxyManagerService $proxyManager;

    public function __construct(ProxyManagerService $proxyManager)
    {
        $this->proxyManager = $proxyManager;
    }

    /**
     * Scrape league standings to map FBref IDs.
     * Comps: 11 (Serie A), 18 (Serie B)
     * Seasons: 2021-2022 format
     */
    public function syncFbrefTeams(int $compId = 11, string $season = '2024-2025'): void
    {
        $url = $this->getFbrefUrl($compId, $season);
        Log::info("FBrefScraper: Navigazione su {$url}");

        $html = $this->fetchWithProxy($url);
        
        if (!$html) {
            Log::error("FBrefScraper: Impossibile recuperare HTML per {$url}");
            return;
        }

        $crawler = new Crawler($html);
        
        // Tabella standard FBref per classifiche: resultsYYYY-YYYYCOMP_overall
        // Cerchiamo tutte le tabelle che contengono 'overall' nell'ID
        $tables = $crawler->filter('table[id*="_overall"]');
        
        if ($tables->count() === 0) {
            Log::warning("FBrefScraper: Tabella standings non trovata in {$url}");
            return;
        }

        $tables->each(function (Crawler $table) {
            $table->filter('tbody tr')->each(function (Crawler $tr) {
                $squadCell = $tr->filter('td[data-stat="team"], td[data-stat="squad"]')->first();
                if ($squadCell->count() === 0) return;

                $link = $squadCell->filter('a')->first();
                if ($link->count() === 0) return;

                $fbrefUrl = "https://fbref.com" . $link->attr('href');
                $name = $link->text();
                
                // Estratto ID dall'URL: /en/squads/ID/Name-Stats
                $parts = explode('/', $link->attr('href'));
                $fbrefId = $parts[3] ?? null;

                if ($fbrefId) {
                    $this->updateOrCreateTeam($name, $fbrefId, $fbrefUrl);
                }
            });
        });
    }

    /**
     * Get the dynamic URL based on Comp and Season.
     */
    protected function getFbrefUrl(int $compId, string $season): string
    {
        $isLive = ($season === '2024-2025');
        $leagueName = ($compId === 11) ? 'Serie-A-Stats' : 'Serie-B-Stats';

        if ($isLive) {
            return "https://fbref.com/en/comps/{$compId}/{$leagueName}";
        }

        return "https://fbref.com/en/comps/{$compId}/{$season}/{$season}-{$leagueName}";
    }

    /**
     * Fetch HTML using Proxy Hub.
     */
    protected function fetchWithProxy(string $url): ?string
    {
        $proxy = $this->proxyManager->getBestProxy();
        if (!$proxy) return null;

        try {
            // Usiamo il provider registrato nel manager
            $providerClass = config('services.proxy_providers.' . $proxy->name) ?? \App\Services\ProxyProviders\ScraperApiProvider::class;
            $provider = app($providerClass);
            
            $proxyUrl = $provider->getProxyUrl($proxy, $url);
            
            $response = Http::timeout(60)->get($proxyUrl);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("FBrefScraper: Proxy {$proxy->name} fallito ({$response->status()}). Mark unreliable.");
            $this->proxyManager->markAsUnreliable($proxy, "Status: " . $response->status());
            
            // Retry once with next best proxy
            return $this->fetchWithProxy($url);

        } catch (\Exception $e) {
            Log::error("FBrefScraper Fetch Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update existing team or create new one if Lookback.
     */
    protected function updateOrCreateTeam(string $name, string $fbrefId, string $fbrefUrl): void
    {
        // Ricerca per nome (api.football-data.org nomi sono simili ma non identici)
        // Usiamo una logica di mapping flessibile o cerchiamo corrispondenza parziale
        $team = Team::where('name', $name)
            ->orWhere('short_name', $name)
            ->first();

        if ($team) {
            $team->update([
                'fbref_id' => $fbrefId,
                'fbref_url' => $fbrefUrl,
                'short_name' => $team->short_name ?: $name // Ripariamo lo short_name se manca
            ]);
            Log::info("FBrefScraper: Mappato '{$name}' -> ID: {$fbrefId}");
        } else {
            // Team non trovato (probabilmente Serie B o Lookback)
            Team::create([
                'name' => $name,
                'short_name' => $name, // Fondamentale per i futuri matching
                'fbref_id' => $fbrefId,
                'fbref_url' => $fbrefUrl,
            ]);
            Log::info("FBrefScraper: Creato nuovo team '{$name}' (ID FBref: {$fbrefId})");
        }
    }
}
