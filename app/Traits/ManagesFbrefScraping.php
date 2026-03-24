<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

trait ManagesFbrefScraping
{
    // Proprietà per la gestione delle pause
    protected $requestCount = 0;
    protected $shortSleepInterval = 1;
    protected $longSleepInterval = 3;
    protected $burstSize = 10;
    
    /**
     * IL NUOVO "MOTORE"
     * Esegue la richiesta HTTP tramite il proxy ScraperAPI.
     * Restituisce un oggetto Crawler con l'HTML pulito.
     */
    protected function fetchPageWithProxy(string $url): Crawler
    {
        $this->performSleep(); // Eseguiamo sempre la pausa
        
        $apiKey = env('SCRAPER_API_KEY');
        if (empty($apiKey)) {
            Log::error("SCRAPER_API_KEY non è impostata nel file .env. Lo scraper fallirà.");
            throw new \Exception("SCRAPER_API_KEY non impostata.");
        }
        
        Log::debug("[Trait ManagesFbrefScraping] Avvio richiesta Proxy API per: {$url}");
        
        // Costruiamo l'URL per ScraperAPI
        $proxyUrl = "http://api.scraperapi.com?api_key={$apiKey}&url=" . urlencode($url);
        
        // Facciamo la richiesta HTTP
        $response = Http::timeout(120)->get($proxyUrl); // Aumentato timeout
        if ($response->successful()) {
            // CATTURA CREDITI DAGLI HEADER DI SCRAPERAPI
            $remaining = $response->header('sa-credits-remaining');
            
            // Possiamo salvarli in una variabile globale o in cache per consultazione rapida
            if ($remaining) {
                \Illuminate\Support\Facades\Cache::put('scraper_credits_last', $remaining, 3600);
                Log::info("[Proxy] Crediti ScraperAPI rimanenti: $remaining");
            }
            
            return new Crawler($response->body());
        }else{
            Log::error("[Trait ManagesFbrefScraping] Proxy API: Richiesta fallita. Status: " . $response->status());
            throw new \Exception("Proxy API: Richiesta fallita (Status: {$response->status()})");
        }
        
        // Otteniamo l'HTML pulito (ScraperAPI ha già gestito JS e commenti)
        $htmlContent = $response->body();
        
        // Salviamo un file di debug (opzionale ma utile)
        $this->saveDebugHtml(new Crawler($htmlContent), 'proxy_success_' . Str::slug(parse_url($url, PHP_URL_PATH)));
        
        return new Crawler($htmlContent);
    }
    
    /**
     * IL NUOVO "PARSER"
     * Questa è la funzione corretta (presa dal tuo Debug command)
     * che usa le chiavi del DB (es. 'games') e non le chiavi FBref (es. 'MP').
     */
    protected function parseTableWithInvertedMap(Crawler $table, array $columnMap): array
    {
        $tableData = [];
        $invertedMap = array_flip($columnMap);
        
        $table->filter('tbody > tr')->each(function (Crawler $row) use (&$tableData, $invertedMap, $columnMap) {
            if ($row->matches('.thead') || $row->matches('.spacer_met')) return;
            
            $playerRow = [];
            
            $playerNameNode = $row->filter('th[data-stat="player"] a')->first();
            if ($playerNameNode->count() > 0) {
                $playerRow['Player'] = trim($playerNameNode->text());
            } else {
                $playerNameNode = $row->filter('th[data-stat="player"]')->first();
                if($playerNameNode->count() > 0) $playerRow['Player'] = trim($playerNameNode->text());
            }
            
            if (empty($playerRow['Player'])) return;
            
            $row->filter('td')->each(function (Crawler $cell) use (&$playerRow, $invertedMap) {
                $statName = $cell->attr('data-stat'); // es. "goals"
                
                if ($statName && isset($invertedMap[$statName])) {
                    // La chiave per il JSON è la chiave del DB (es. 'goals')
                    $playerRow[$statName] = trim($cell->text());
                }
            });
                
                if (count($playerRow) > 1) {
                    $tableData[] = $playerRow;
                }
        });
            
            return $tableData;
    }
    
    /**
     * HELPER: Gestione Pause (dal tuo file originale)
     */
    protected function performSleep(): void
    {
        $this->requestCount++;
        if ($this->requestCount > 1) {
            if ($this->requestCount % $this->burstSize === 0) {
                Log::debug("[Trait ManagesFbrefScraping] Pausa lunga: {$this->longSleepInterval}s");
                sleep($this->longSleepInterval);
            } else {
                Log::debug("[Trait ManagesFbrefScraping] Pausa corta: {$this->shortSleepInterval}s");
                sleep($this->shortSleepInterval);
            }
        }
    }
    
    /**
     * HELPER: Salvataggio HTML (dal tuo file originale)
     */
    protected function saveDebugHtml(Crawler $crawler, string $fileName): void
    {
        $debugPath = storage_path('app/debug_html');
        if (!File::isDirectory($debugPath)) {
            File::makeDirectory($debugPath, 0755, true);
        }
        $filename = Str::slug($fileName) . '_' . date('Y-m-d_H-i-s') . '.html';
        File::put($debugPath . '/' . $filename, $crawler->html());
    }
    
    /**
     * HELPER: Scommentare (lo teniamo per sicurezza, anche se ScraperAPI non ne ha bisogno)
     */
    protected function uncommentHiddenHtml(string $htmlContent): string
    {
        return preg_replace('//s', '$1', $htmlContent);
    }
}