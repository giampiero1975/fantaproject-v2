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

        // Timeout alzato a 120s per supportare JS rendering (premium=true + render=true)
        // su pagine FBref pesanti con ScraperAPI e ZenRows
        $response = Http::timeout(120)->withoutVerifying()->get($proxyUrl);
        $body = $response->body();
        
        Log::debug("[Trait ManagesFbrefScraping] Response Snippet: " . substr($body, 0, 500));

        if ($response->successful()) {
            return new Crawler($body);
        } else {
            Log::error("[Trait ManagesFbrefScraping] Proxy '{$proxy->name}': Richiesta fallita (Status: {$response->status()}) Body: " . substr($body, 0, 100));
            throw new \Exception("Proxy '{$proxy->name}': Richiesta fallita (Status: {$response->status()})");
        }
    }

    /**
     * Recupera l'HTML grezzo (stringa) tramite proxy.
     * Identico a fetchPageWithProxy ma ritorna il body come stringa invece del Crawler.
     * Utilizzato da FbrefScrapingService::scrapeTeamStats() per lo svelamento degli HTML commentati.
     */
    protected function fetchRawHtmlWithProxy(string $url, bool $render = false): string
    {
        $this->performSleep();

        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();

        if (!$proxy) {
            Log::error("[Trait ManagesFbrefScraping] Nessun proxy attivo disponibile.");
            throw new \Exception("Nessun proxy attivo disponibile.");
        }

        $proxyUrl = $proxyManager->getProxyUrl($proxy, $url, ['render' => $render]);

        Log::debug("[Trait ManagesFbrefScraping::fetchRawHtmlWithProxy] GET {$proxyUrl}");

        $response = Http::timeout(120)->withoutVerifying()->get($proxyUrl);
        $body     = $response->body();

        if ($response->successful()) {
            Log::debug("[Trait ManagesFbrefScraping::fetchRawHtmlWithProxy] OK " . strlen($body) . " bytes");
            return $body;
        }

        Log::error("[Trait ManagesFbrefScraping::fetchRawHtmlWithProxy] Fallita (Status: {$response->status()})");
        throw new \Exception("Proxy '{$proxy->name}': Richiesta fallita (Status: {$response->status()})");
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
                $rawUrl = $playerNameNode->attr('href');
                if ($rawUrl) {
                    $fullUrl = str_starts_with($rawUrl, 'http') ? $rawUrl : 'https://fbref.com' . $rawUrl;
                    $playerRow['fbref_url_extracted'] = $fullUrl;
                    if (preg_match('/players\/([a-f0-9]+)/', $fullUrl, $m)) {
                        $playerRow['fbref_id_extracted'] = $m[1];
                    }
                }
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
    
    /**
     * Salvataggio chirurgico dell'HTML per debug e "Black Box".
     */
    protected function saveDebugHtml(string $html, string $teamName, string $season, string $type = 'team_stats'): string
    {
        $basePath = "scraping/fbref/html/{$season}/" . Str::slug($teamName);
        $directory = storage_path("logs/{$basePath}");
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = "{$type}_" . date('Ymd_Hi') . ".html";
        $fullPath = "{$directory}/{$filename}";
        
        File::put($fullPath, $html);
        return $fullPath;
    }

    /**
     * Salvataggio chirurgico del JSON estratto.
     */
    protected function saveDebugJson(array $data, string $teamName, string $season, string $type = 'team_stats'): string
    {
        $basePath = "scraping/fbref/json/{$season}/" . Str::slug($teamName);
        $directory = storage_path("logs/{$basePath}");

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = "{$type}_" . date('Ymd_Hi') . ".json";
        $fullPath = "{$directory}/{$filename}";

        File::put($fullPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $fullPath;
    }

    protected function scrapeTable(Crawler $crawler, string $tableId, array $columns): array
    {
        // Selettore flessibile per gestire ID dinamici (es. stats_standard_11)
        $table = $crawler->filter("table[id^='{$tableId}']");
        if ($table->count() === 0) return [];

        return $this->parseTableWithInvertedMap($table, $columns);
    }

    /**
     * Svela il contenuto HTML commentato da FBref.
     */
    public function uncommentHiddenHtml(string $html): string
    {
        return preg_replace_callback('/<!--(.*?<table.*?)-->/s', function($m) {
            return $m[1];
        }, $html);
    }
}
