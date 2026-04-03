<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use App\Services\ProxyManagerService;

trait ManagesFbrefScraping
{
    protected $requestCount = 0;
    protected $shortSleepInterval = 1;
    protected $longSleepInterval = 3;
    protected $burstSize = 10;
    
    protected function fetchPageWithProxy(string $url): Crawler
    {
        $this->performSleep(); 
        
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();

        if (!$proxy) {
            Log::error("[Trait ManagesFbrefScraping] Nessun proxy attivo disponibile.");
            throw new \Exception("Nessun proxy attivo disponibile.");
        }
        
        Log::debug("[Trait ManagesFbrefScraping] Avvio richiesta via Proxy '{$proxy->name}' per: {$url}");
        
        $proxyUrl = $proxyManager->getProxyUrl($proxy, $url);
        
        Log::debug("[Trait ManagesFbrefScraping] ZenRows API URL: {$proxyUrl}");

        // Torniamo alla modalità diretta (API URL) che non fallisce il parsing in Guzzle
        // Timeout fissato a 15 secondi richiesto
        $response = Http::timeout(15)->withoutVerifying()->get($proxyUrl);
        $body = $response->body();
        
        Log::debug("[Trait ManagesFbrefScraping] Response Snippet: " . substr($body, 0, 500));

        if ($response->successful()) {
            return new Crawler($body);
        } else {
            Log::error("[Trait ManagesFbrefScraping] Proxy '{$proxy->name}': Richiesta fallita (Status: {$response->status()}) Body: " . substr($body, 0, 100));
            throw new \Exception("Proxy '{$proxy->name}': Richiesta fallita (Status: {$response->status()})");
        }
    }
    
    protected function parseTableWithInvertedMap(Crawler $table, array $columnMap): array
    {
        $tableData = [];
        $invertedMap = array_flip($columnMap);
        
        $table->filter('tbody > tr')->each(function (Crawler $row) use (&$tableData, $invertedMap) {
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
                $statName = $cell->attr('data-stat');
                
                if ($statName && isset($invertedMap[$statName])) {
                    $playerRow[$statName] = trim($cell->text());
                }
            });
                
            if (count($playerRow) > 1) {
                $tableData[] = $playerRow;
            }
        });
            
        return $tableData;
    }
    
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
    
    protected function saveDebugHtml(Crawler $crawler, string $fileName): void
    {
        $debugPath = storage_path('app/debug_html');
        if (!File::isDirectory($debugPath)) {
            File::makeDirectory($debugPath, 0755, true);
        }
        $filename = Str::slug($fileName) . '_' . date('Y-m-d_H-i-s') . '.html';
        File::put($debugPath . '/' . $filename, $crawler->html());
    }

    protected function scrapeTable(Crawler $crawler, string $tableId, array $columns): array
    {
        $table = $crawler->filter("table#{$tableId}");
        if ($table->count() === 0) return [];

        return $this->parseTableWithInvertedMap($table, $columns);
    }
}
