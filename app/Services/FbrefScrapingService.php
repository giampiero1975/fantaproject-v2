<?php
namespace App\Services;

use App\Traits\ManagesFbrefScraping;
use App\Traits\FindsPlayerByName; // <-- NUOVO TRAIT
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

class FbrefScrapingService
{
    use ManagesFbrefScraping, FindsPlayerByName;

    protected $targetUrl;

    public function __construct()
    {
        // Il costruttore ora è pulito!
        // La chiave API è gestita direttamente dal Trait.
    }

    public function setTargetUrl(string $url): self
    {
        $this->targetUrl = $url;
        return $this;
    }

    /**
     * ORA QUESTA FUNZIONE È "MAGRA":
     * Chiama solo le funzioni del Trait (Motore + Parser)
     */
    public function scrapeTeamStats(bool $render = false): array
    {
        if (empty($this->targetUrl)) {
            Log::error('URL della squadra non impostato.');
            return ['error' => 'URL della squadra non impostato.'];
        }

        $teamStatsSchema = config('fbref_schemas.team_player_stats', []);
        if (empty($teamStatsSchema)) {
            Log::error('Schema "team_player_stats" non trovato.');
            return ['error' => 'Schema "team_player_stats" non trovato.'];
        }

        try {
            // 1. MOTORE (dal Trait): Recuperiamo il BODY grezzo per lo svelamento
            $rawHtml = $this->fetchRawHtmlWithProxy($this->targetUrl, $render);

            // 2. SVELAMENTO: Operiamo sulla stringa originale (Molto più robusto)
            $uncommentedHtml = $this->uncommentHiddenHtml($rawHtml);
            
            // Salvataggio Black Box HTML (Debug)
            $this->saveDebugHtml($uncommentedHtml, 'team_stats', '2024', 'raw');
            
            // 3. PARSING: Usiamo la logica centralizzata basata sugli schemi
            $allTablesData = $this->parseDataFromHtml($uncommentedHtml, 'team_player_stats');

            if (empty($allTablesData)) {
                Log::error("Nessun dato valido trovato per URL: {$this->targetUrl}");
                return ['error' => 'Nessun dato valido estratto.'];
            }

            Log::info("Scraping (scrapeTeamStats) completato con successo per: {$this->targetUrl}");
            
            // Salvataggio Debug (JSON)
            $this->saveDebugJson($allTablesData, basename($this->targetUrl), "Debug");
            
            return $allTablesData;

        } catch (\Exception $e) {
            Log::error("Errore (scrapeTeamStats): " . $e->getMessage());
            return ['error' => 'Errore Scraping: ' . $e->getMessage()];
        }
    }

    /**
     * Estrae la classifica di Serie A per mappare Squadre -> URL
     * URL: https://fbref.com/en/comps/11/Serie-A-Stats
     */
    public function scrapeSerieAStandings(?int $year = null): array
    {
        // Default URL per la stagione corrente
        $url = "https://fbref.com/en/comps/11/Serie-A-Stats";
        
        if ($year) {
            // Formato richiesto: 2025-2026/2025-2026-Serie-A-Stats
            $nextYear = $year + 1;
            $season = "{$year}-{$nextYear}";
            $url = "https://fbref.com/en/comps/11/{$season}/{$season}-Serie-A-Stats";
        }

        Log::info("Inizio Scraping Classifica Serie A: {$url}");

        try {
            $crawler = $this->fetchPageWithProxy($url);
            
            // Cerchiamo la tabella della classifica
            // Prova 1: ID che inizia con 'results' (standard FBref)
            $table = $crawler->filter('table[id^="results"]')->first();
            
            // Prova 2: Se la Prova 1 fallisce, cerca qualsiasi stats_table che contenga la classifica
            if ($table->count() === 0) {
                $table = $crawler->filter('table.stats_table')->filterXPath('//th[contains(text(), "Squad") or contains(text(), "Team")]/ancestor::table')->first();
            }

            if ($table->count() === 0) {
                // Prova 3: Prendi semplicemente la prima stats_table se presente
                $table = $crawler->filter('table.stats_table')->first();
            }
            
            if ($table->count() === 0) {
                throw new \Exception("Tabella classifica non trovata nella pagina. Ids trovati: " . implode(', ', $crawler->filter('table')->each(fn($n) => $n->attr('id'))));
            }

            $teams = [];
            $table->filter('tbody tr')->each(function (Crawler $row) use (&$teams) {
                $squadNode = $row->filter('td[data-stat="team"] a')->first();
                if ($squadNode->count() > 0) {
                    $teamName = trim($squadNode->text());
                    $teamUrl = $squadNode->attr('href');
                    
                    if (!str_starts_with($teamUrl, 'http')) {
                        $teamUrl = "https://fbref.com" . $teamUrl;
                    }

                    // Estrazione ID (es. dcce17c0)
                    $fbrefId = null;
                    if (preg_match('/squads\/([a-f0-9]+)\//', $teamUrl, $matches)) {
                        $fbrefId = $matches[1];
                    }

                    $teams[] = [
                        'fbref_name' => $teamName,
                        'fbref_url'  => $teamUrl,
                        'fbref_id'   => $fbrefId
                    ];
                }
            });

            Log::info("Classifica estratta: " . count($teams) . " squadre trovate.");
            return $teams;

        } catch (\Exception $e) {
            Log::error("Errore durante lo scraping della classifica: " . $e->getMessage());
            return []; // Ritorna vuoto per permettere il fallback manuale per ogni team
        }

    }


    /**
     * ANCHE QUESTA FUNZIONE ORA È "MAGRA":
     * Chiama solo il "MOTORE" del Trait e la sua logica di parsing.
     */
    public function searchPlayerFbrefUrlByName(string $playerName, ?string $playerTeamShortName = null): ?string
    {


        $encodedPlayerName = urlencode($playerName);
        $searchUrl = "https://fbref.com/en/search/search.fcgi?search={$encodedPlayerName}";
        Log::info("Inizio ricerca FBref (via Trait) per: '{$playerName}'");

        try {
            // 1. MOTORE (dal Trait)
            $crawler = $this->fetchPageWithProxy($searchUrl);

            // 2. LOGICA DI CONTROLLO (che non richiede schema)
            $playerH1 = $crawler->filter('#meta h1');
            if ($playerH1->count() > 0) {
                $canonicalLink = $crawler->filter('link[rel="canonical"]');
                if ($canonicalLink->count() > 0) {
                    $finalUrl = $canonicalLink->attr('href');
                    Log::info("Trovato URL FBref per '{$playerName}' (Redirect via Proxy): {$finalUrl}");
                    return $finalUrl;
                }
            }

            Log::warning("La ricerca per '{$playerName}' non ha reindirizzato. Analizzo i risultati...");

            // --- INIZIO LOGICA DI PUNTEGGIO CORRETTA (presa dal tuo file originale) ---
            $searchResults = $crawler->filter('div.search-item');

            if ($searchResults->count() > 0) {
                Log::debug("Trovati {$searchResults->count()} elementi di ricerca potenziali. Analizzo i testi:");

                $bestMatchUrl = null;
                $bestMatchScore = - 1;
                $playerNameLower = Str::lower(trim($playerName));
                $playerTeamShortNameLower = $playerTeamShortName ? Str::lower(trim($playerTeamShortName)) : null;

                foreach ($searchResults as $node) {
                    $itemCrawler = new Crawler($node);
                    $playerLinkNode = $itemCrawler->filter('.search-item-name a');

                    if ($playerLinkNode->count() === 0) {
                        Log::debug("Saltato elemento senza link del giocatore.");
                        continue;
                    }

                    $fullUrl = 'https://fbref.com' . $playerLinkNode->attr('href');
                    $linkText = trim($playerLinkNode->text());
                    $linkTextLower = Str::lower($linkText);

                    $teamText = $itemCrawler->filter('.search-item-team')->count() > 0 ? Str::lower(trim($itemCrawler->filter('.search-item-team')->text())) : null;

                    $currentScore = 0;

                    if ($linkTextLower === $playerNameLower) {
                        $currentScore += 100;
                    } elseif (Str::contains($linkTextLower, $playerNameLower)) {
                        $currentScore += 50;
                    }

                    if ($playerTeamShortNameLower && $teamText) {
                        if (Str::contains($teamText, $playerTeamShortNameLower)) {
                            $currentScore += 30;
                        } else {
                            // Qui manca la funzione 'calculateTeamNameSimilarity',
                            // dobbiamo aggiungerla al Trait o al Servizio.
                            // Per ora, continuiamo con la logica semplice.
                        }
                    }

                    Log::debug("Risultato: '{$linkText}' | Squadra FBref: '{$teamText}' | URL: '{$fullUrl}' | Score: {$currentScore}");

                    if ($currentScore > $bestMatchScore) {
                        $bestMatchScore = $currentScore;
                        $bestMatchUrl = $fullUrl;
                    }
                }

                if ($bestMatchUrl && $bestMatchScore > 0) {
                    Log::info("Trovato il miglior URL FBref per '{$playerName}' (Score: {$bestMatchScore}): {$bestMatchUrl}");
                    return $bestMatchUrl;
                }
            }

            Log::warning("URL FBref non trovato per '{$playerName}'.");
            return null;
        } catch (\Exception $e) {
            Log::error("Errore (searchPlayerFbrefUrlByName) durante la ricerca: " . $e->getMessage());
            return null;
        }
    }



    /**
     * Cerca l'URL di una squadra su FBref tramite il motore di ricerca interno.
     */

    public function searchTeamFbrefUrlByName(string $teamName): ?string
    {
        $encodedTeamName = urlencode($teamName);
        $searchUrl = "https://fbref.com/en/search/search.fcgi?search={$encodedTeamName}";
        Log::info("Inizio ricerca Team FBref per: '{$teamName}'");

        try {
            $crawler = $this->fetchPageWithProxy($searchUrl);

            // Se veniamo reindirizzati direttamente alla pagina del team
            $teamH1 = $crawler->filter('#meta h1');
            if ($teamH1->count() > 0) {
                $canonicalLink = $crawler->filter('link[rel="canonical"]');
                if ($canonicalLink->count() > 0) {
                    $finalUrl = $canonicalLink->attr('href');
                    if (str_contains($finalUrl, '/squads/')) {
                        Log::info("Trovato URL Team per '{$teamName}' (Redirect): {$finalUrl}");
                        return $finalUrl;
                    }
                }
            }

            // Altrimenti cerchiamo nei risultati di ricerca
            // Prova 1: Selettore specifico div.search-item
            $teamLink = $crawler->filter('div.search-item a')->filterXPath('//a[contains(@href, "/squads/")]')->first();
            
            // Prova 2: Qualsiasi link che contenga /squads/ (fallback drastico)
            if ($teamLink->count() === 0) {
                $teamLink = $crawler->filter('a[href*="/squads/"]')->first();
            }

            if ($teamLink->count() > 0) {
                $finalUrl = $teamLink->attr('href');
                if (!str_starts_with($finalUrl, 'http')) {
                    $finalUrl = "https://fbref.com" . $finalUrl;
                }
                Log::info("Trovato URL Team per '{$teamName}' (Search Result): {$finalUrl}");
                return $finalUrl;
            }

            Log::warning("URL Team FBref non trovato per '{$teamName}'. Salvataggio HTML per debug...");
            file_put_contents(storage_path('logs/debug_fbref_search.html'), $crawler->html());
            return null;
        } catch (\Exception $e) {
            Log::error("Errore (searchTeamFbrefUrlByName) per '{$teamName}': " . $e->getMessage());
            return null;
        }
    }


    /**
     * Verifica lo stato del proxy e i crediti rimanenti tramite ProxyManagerService.
     */
    public function checkProxyHealth(): array
    {
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();

        if (!$proxy) {
            return [
                'ok' => false,
                'error' => "Nessun proxy attivo trovato",
                'remaining_credits' => 0
            ];
        }

        $success = $proxyManager->testConnection($proxy);

        return [
            'ok' => $success,
            'remaining_credits' => $proxy->limit_monthly - $proxy->current_usage,
            'name' => $proxy->name
        ];
    }

    /**
     * IL CERVELLO: Sceglie automaticamente tra Modalità DIRETTA e PIPELINE.
     */
    public function dispatchScrape(array $urls, array $context = [])
    {
        $count = count($urls);
        $mode = $count <= 3 ? 'direct' : 'pipeline';
        
        // Creazione record Job SEMPRE (Richiesta Socio)
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();
        
        $jobId = $mode === 'direct' ? 'direct_' . uniqid() : 'pending_' . uniqid();
        
        $job = \App\Models\ScrapingJob::create([
            'job_id' => $jobId,
            'proxy_service_id' => $proxy?->id ?? 1,
            'mode' => $mode,
            'status' => 'pending',
            'service' => $mode === 'direct' ? 'ScraperAPI_Direct' : 'ScraperAPI_Pipeline',
            'payload' => $urls
        ]);

        if ($mode === 'direct') {
            return $this->executeDirectScrape($urls, $job, $context);
        }

        return $this->executePipelineScrape($urls, $job, $context);
    }

    /**
     * Modalità DIRETTA (Sincrona)
     */
    private function executeDirectScrape(array $urls, \App\Models\ScrapingJob $job, array $context)
    {
        $results = [];
        $startTime = microtime(true);
        $totalCredits = 0;

        foreach ($urls as $url) {
            $job->update(['status' => 'running']);
            $response = $this->testProxyCall($url, $context['render'] ?? false);
            
            if ($response->successful()) {
                $credits = (float) $response->header('sa-credit-cost', 10);
                $totalCredits += $credits;
                
                // Aggiorniamo il ProxyManager per la persistenza del costo
                app(ProxyManagerService::class)->setLastRequestCost($credits);

                $this->processTeamImport(
                    $response->body(), 
                    $url, 
                    $context['team_id'], 
                    $context['season_year'],
                    [
                        'latency_ms' => round((microtime(true) - $startTime) * 1000),
                        'sa_headers' => $response->headers()
                    ]
                );

                $results[$url] = 'success';
            } else {
                $results[$url] = 'failed';
            }
        }

        $job->update([
            'status' => count(array_filter($results, fn($r) => $r === 'success')) > 0 ? 'finished' : 'failed',
            'duration' => round(microtime(true) - $startTime, 2),
            'credits_spent' => $totalCredits,
            'processed_at' => now()
        ]);

        return $results;
    }

    /**
     * Modalità PIPELINE (Asincrona via Webhook)
     */
    private function executePipelineScrape(array $urls, \App\Models\ScrapingJob $job, array $context)
    {
        $proxyManager = app(\App\Services\ProxyManagerService::class);
        $proxy = $job->proxyService;
        
        // Costruzione URL Webhook con segreto
        $webhookUrl = config('app.url') . '/api/scraper-webhook?secret=' . env('SCRAPER_WEBHOOK_SECRET');

        $response = \Illuminate\Support\Facades\Http::timeout(60)->post('https://async.scraperapi.com/batchjobs', [
            'apiKey' => $proxy->api_key,
            'urls' => $urls,
            'premium' => true,
            'max_cost' => 10,
            'callback' => [
                'type' => 'webhook',
                'url' => $webhookUrl
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $idData = is_array($data) && isset($data[0]) ? $data[0] : $data;
            
            $job->update([
                'job_id' => $idData['id'] ?? $job->job_id,
            ]);
            
            return $job;
        }

        $job->update(['status' => 'failed']);
        throw new \Exception("Fallimento inizializzazione Pipeline: " . $response->body());
    }

    /**
     * Processa l'importazione di una squadra, gestendo anagrafiche e statistiche.
     * 
     * @param string $html
     * @param string $url
     * @param int $teamId
     * @param string $seasonYear
     * @param array $options Opzioni aggiuntive (is_surgical, dry_run, etc.)
     * @return bool
     */
    public function processTeamImport(string $html, string $url, int $teamId, string $seasonYear, array $options = []): bool
    {
        $uncommentedHtml = $this->uncommentHiddenHtml($html);
        $scrapedData = $this->parseDataFromHtml($uncommentedHtml, 'team_player_stats');
        
        if (empty($scrapedData)) {
            Log::error("[FbrefScrapingService] Nessun dato estratto per Team ID {$teamId}");
            return false;
        }

        $aggregated = $this->aggregatePlayerData($scrapedData);
        $isSurgical = $options['is_surgical'] ?? false;
        $dryRun = $options['dry_run'] ?? false;
        
        // Salvataggio Artefatti (Black Box)
        $this->saveArtifacts($html, $url, $teamId, $seasonYear, $aggregated, $options);

        // Risoluzione Stagione
        $season = \App\Models\Season::where('season_year', $seasonYear)->first();
        if (!$season) throw new \Exception("Stagione {$seasonYear} non trovata");
        $isPastSeason = (int)$season->season_year < 2024;

        $stats = ['matched' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($aggregated as $fbrefName => $data) {
            try {
                $fbrefId = $data['fbref_id_extracted'] ?? null;
                $fbrefUrl = $data['fbref_url_extracted'] ?? null;
                
                // 1. MATCHING CHIRURGICO (Esteso a withTrashed)
                $player = $this->findLocalPlayer($fbrefName, $fbrefId, $teamId);

                // 2. ECCEZIONE LOST-ONE (Stagioni Storiche)
                if (!$player && $isPastSeason) {
                    $localPlayers = \App\Models\Player::withTrashed()
                        ->whereHas('rosters', function ($q) use ($teamId, $season) {
                            $q->where('team_id', $teamId)->where('season_id', $season->id);
                        })->get();

                    foreach ($localPlayers as $lp) {
                        if ($this->namesAreSimilar((string)$fbrefName, (string)$lp->name)) {
                            $player = $lp;
                            break;
                        }
                    }
                }

                if (!$player) {
                    Log::warning("[FbrefScrapingService] Giocatore non trovato (skipped): {$fbrefName}");
                    $stats['skipped']++;
                    continue;
                }

                $stats['matched']++;

                // [PROTOCOLLO SICUREZZA] Regola No Overwrite per IDs
                if (!empty($fbrefId)) {
                    if (empty($player->fbref_id)) {
                        // Caso 1: Campo NULL, procediamo all'aggiornamento
                        if (!$dryRun) {
                            $player->update([
                                'fbref_id' => $fbrefId,
                                'fbref_url' => $fbrefUrl ?? $player->fbref_url
                            ]);
                        }
                        $stats['updated']++;
                    } elseif ($player->fbref_id !== $fbrefId) {
                        // Caso 2: Mismatch presente, NON sovrascriviamo, logghiamo Warning
                        Log::warning("[Fbref Match Collision] Mismatch per '{$player->name}' (ID: {$player->id}). DB: {$player->fbref_id} | Scraper: {$fbrefId}. Aggiornamento annullato per sicurezza.");
                    }
                }

                // 3. Salvataggio Statistiche (Saltato se is_surgical)
                if (!$isSurgical) {
                    $dataToSave = $this->prepareLayeredData($data);
                    $dataToSave['season_id'] = $season->id;
                    $dataToSave['deleted_at'] = null;

                    if (!$dryRun) {
                        \App\Models\PlayerFbrefStat::withTrashed()->updateOrCreate(
                            [
                                'player_id' => $player->id,
                                'team_id' => $teamId,
                                'season_id' => $season->id,
                            ],
                            $dataToSave
                        );
                    }
                }

            } catch (\Exception $e) {
                Log::error("[FbrefScrapingService] Errore critico per player {$fbrefName}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("[FbrefScrapingService] Processamento completato per Team {$teamId} ({$seasonYear}). Risultati: ", $stats);
        return true;
    }

    private function aggregatePlayerData(array $scrapedData): array
    {
        $aggregated = [];
        foreach ($scrapedData as $rows) {
            foreach ($rows as $row) {
                $key = $row['fbref_id_extracted'] ?? ($row['Player'] ?? null);
                if (!$key) continue;

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = $row;
                } else {
                    foreach ($row as $field => $value) {
                        if (!array_key_exists($field, $aggregated[$key]) || empty($aggregated[$key][$field])) {
                            $aggregated[$key][$field] = $value;
                        }
                    }
                }
            }
        }
        return $aggregated;
    }

    private function findLocalPlayer(string $name, ?string $fbrefId, int $teamId): ?\App\Models\Player
    {
        if ($fbrefId) {
            $p = \App\Models\Player::where('fbref_id', $fbrefId)->first();
            if ($p) return $p;
        }
        return $this->findPlayer(['name' => $name], \App\Models\Team::find($teamId));
    }

    private function prepareLayeredData(array $data): array
    {
        $layered = [];
        $layer1Columns = [
            'games', 'games_starts', 'minutes', 'minutes_90s',
            'goals', 'assists', 'cards_yellow', 'cards_red'
        ];
        
        foreach ($layer1Columns as $col) {
            if (isset($data[$col])) {
                $cleanValue = is_string($data[$col]) ? str_replace(',', '', $data[$col]) : $data[$col];
                $layered[$col] = ($cleanValue === '' || $cleanValue === null) ? null : $cleanValue;
            }
        }
        $layered['data_team'] = $data;
        return $layered;
    }

    private function saveArtifacts(string $html, string $url, int $teamId, string $seasonYear, array $json, ?array $costAnalysis = null): void
    {
        $timestamp = now()->format('Ymd_His');
        $fileName = "team_{$teamId}_{$timestamp}";
        
        // HTML
        $htmlPath = $this->saveDebugHtml($html, "team_{$teamId}", $seasonYear, 'async_team');
        
        // JSON
        $jsonPath = $this->saveDebugJson($json, "team_{$teamId}", $seasonYear, 'async_team');

        Log::debug("[FbrefScrapingService] Artefatti salvati per Team {$teamId}");
    }

    /**
     * Ponte pubblico per il test del proxy e l'uso esterno
     */
    public function testProxyCall(string $url, bool $render = false)
    {
        $proxyManager = app(\App\Services\ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();
        
        if (!$proxy) {
            throw new \Exception("Nessun proxy attivo trovato");
        }

        $this->lastProxy = $proxy; // PERSISTENZA PER AUDIT
        $proxyUrl = $proxyManager->getProxyUrl($proxy, $url, ['render' => $render]);
        return \Illuminate\Support\Facades\Http::timeout(120)->withoutVerifying()->get($proxyUrl);
    }

    public function getRemainingCredits()
    {
        $proxy = app(ProxyManagerService::class)->getActiveProxy();
        return $proxy ? ($proxy->limit_monthly - $proxy->current_usage) : 0;
    }

    /**
     * Metodo PONTE corretto
     */
    public function parseDataFromHtml(string $html, string $schemaKey): array
    {
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $data = [];

        // Recuperiamo lo schema dal config
        $schema = config("fbref_schemas.{$schemaKey}");

        if (! $schema) {
            return [
                'error' => "Schema {$schemaKey} non trovato"
            ];
        }

        // Usiamo il metodo 'scrapeTable' supportando sia il formato flat che quello nestato
        foreach ($schema as $key => $config) {
            $tableId = $config['id'] ?? $key; // Fallback alla chiave se 'id' non esiste
            $columns = $config['columns'] ?? $config; // Fallback al valore se 'columns' non esiste
            
            $tableData = $this->scrapeTable($crawler, $tableId, $columns);
            if (! empty($tableData)) {
                $data[$key] = $tableData;
            }
        }

        return $data;
    }

    /**
     * Test di estrazione da file locale corretto
     */
    public function scrapeFromLocalFile(string $filePath, string $schemaKey): array
    {
        if (! file_exists($filePath)) {
            throw new \Exception("File non trovato: $filePath");
        }

        $html = file_get_contents($filePath);
        return $this->parseDataFromHtml($html, $schemaKey);
    }

    /**
     * Sincronizza i dati dei calciatori con il database (Mapping).
     */
    /**
     * Sincronizza i dati dei calciatori con il database (Mapping Rose).
     * 
     * @param array $playersData
     * @param int $teamId
     * @param int $seasonId
     * @param bool $dryRun
     * @return array
     */
    public function syncPlayersData(array $playersData, int $teamId, int $seasonId, bool $dryRun = false): array
    {
        $results = [
            'total' => count($playersData),
            'matched' => 0,
            'updated' => 0,
            'noise' => 0,
            'log' => []
        ];

        $season = \App\Models\Season::find($seasonId);
        $isPastSeason = $season && (int)$season->season_year < 2024;

        // Recupero Roster Locale (Include soft-deleted per copertura totale)
        $localPlayers = \App\Models\Player::withTrashed()
            ->whereHas('rosters', function ($q) use ($teamId, $seasonId) {
                $q->where('team_id', $teamId)->where('season_id', $seasonId);
            })
            ->get();

        foreach ($playersData as $sPlayer) {
            $sName = $sPlayer['Player'] ?? null;
            $sUrl  = $sPlayer['fbref_url_extracted'] ?? null;
            $sId   = $sPlayer['fbref_id_extracted'] ?? null;

            if (!$sName || !$sUrl) continue;

            $bestMatch = null;

            foreach ($localPlayers as $lPlayer) {
                // Precision Match via fbref_id
                if (!empty($lPlayer->fbref_id) && $lPlayer->fbref_id === $sId) {
                    $bestMatch = $lPlayer;
                    break;
                }
                // Fuzzy Match via Nome
                if ($this->namesAreSimilar((string)$sName, (string)$lPlayer->name)) {
                    $bestMatch = $lPlayer;
                    break;
                }
            }

            if ($bestMatch) {
                $results['matched']++;
                
                // [PROTOCOLLO SICUREZZA] Regola No Overwrite
                if (empty($bestMatch->fbref_id)) {
                    $results['updated']++;
                    if (!$dryRun) {
                        $bestMatch->update(['fbref_id' => $sId, 'fbref_url' => $sUrl]);
                        $results['log'][] = "✅ MAPPATO: '{$sName}' -> '{$bestMatch->name}' (ID: {$sId})";
                    } else {
                        $results['log'][] = "🧪 [DRY-RUN] MAPPATURA: '{$sName}' -> '{$bestMatch->name}'";
                    }
                } elseif ($bestMatch->fbref_id !== $sId) {
                    $results['log'][] = "⚠️ MISMATCH: '{$sName}' ha ID {$sId} su FBref, ma DB ha {$bestMatch->fbref_id}. Salto aggiornamento.";
                    Log::warning("[Fbref Sync Mismatch] Impossibile aggiornare '{$bestMatch->name}'. Campo già popolato e diverso.");
                } else {
                    $results['log'][] = "ℹ️ GIA' MAPPATO: '{$sName}'";
                }
            } else {
                $results['noise']++;
                $results['log'][] = "❓ NO MATCH: '{$sName}'";
            }
        }

        return $results;
    }


    /**
     * Algoritmo di similarità per matching "Lostone Storico"
     */
    private function namesAreSimilar(string $name1, string $name2): bool
    {
        $clean1 = $this->normalizeName($name1);
        $clean2 = $this->normalizeName($name2);

        // Caso 1: Match esatto post-norm
        if ($clean1 === $clean2) return true;

        // Caso 2: Similarità testuale (Soglia 80% per i giocatori)
        similar_text($clean1, $clean2, $percent);

        return $percent >= 80;
    }

    private function normalizeName(string $name): string
    {
        // Rimuove accenti e trasforma in minuscole
        $name = strtolower(\Illuminate\Support\Str::ascii($name));
        // Rimuove punteggiatura e spazi extra
        $name = preg_replace('/[^a-z0-9 ]/', '', $name);
        return trim($name);
    }
}