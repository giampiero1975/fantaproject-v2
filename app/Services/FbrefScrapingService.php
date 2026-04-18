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
    public function scrapeTeamStats(): array
    {
        if (empty($this->targetUrl)) {
            Log::error('URL della squadra non impostato.');
            return [
                'error' => 'URL della squadra non impostato.'
            ];
        }

        $teamStatsSchema = config('fbref_schemas.team_player_stats', []);
        if (empty($teamStatsSchema)) {
            Log::error('Schema "team_player_stats" non trovato.');
            return [
                'error' => 'Schema "team_player_stats" non trovato.'
            ];
        }

        try {
            // 1. MOTORE (dal Trait)
            $crawler = $this->fetchPageWithProxy($this->targetUrl);
            
            // Salvataggio Debug (HTML)
            $this->saveDebugHtml($crawler->html(), basename($this->targetUrl), "Debug");
        } catch (\Exception $e) {
            Log::error("Errore (scrapeTeamStats) durante la richiesta al Proxy API: " . $e->getMessage());
            return [
                'error' => 'Errore Proxy API: ' . $e->getMessage()
            ];
        }

        $allTablesData = [];

        $crawler->filter('table.stats_table')->each(function (Crawler $tableNode) use (&$allTablesData, $teamStatsSchema) {
            $tableId = $tableNode->attr('id');
            if (empty($tableId))
                return;

            $cleanTableId = null;
            foreach (array_keys($teamStatsSchema) as $schemaKey) {
                if (str_starts_with($tableId, $schemaKey)) {
                    $cleanTableId = $schemaKey;
                    break;
                }
            }

            if ($cleanTableId) {
                $columnMap = $teamStatsSchema[$cleanTableId];
                Log::info("Tabella '{$tableId}' trovata e mappata (Schema: '{$cleanTableId}').");

                // 2. PARSER (dal Trait)
                $allTablesData[$cleanTableId] = $this->parseTableWithInvertedMap($tableNode, $columnMap);
            } else {
                Log::warning("Tabella '{$tableId}' (Pulito: '{$cleanTableId}') trovata ma ignorata.");
            }
        });

        if (empty($allTablesData)) {
            Log::error("Nessun dato valido trovato per URL: {$this->targetUrl}");
            return [
                'error' => 'Nessun dato valido estratto.'
            ];
        }

        Log::info("Scraping (scrapeTeamStats) completato con successo per: {$this->targetUrl}");

        // Salvataggio Debug (JSON)
        $this->saveDebugJson($allTablesData, basename($this->targetUrl), "Debug");
        return $allTablesData;
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
     * Ponte pubblico per il test del proxy e l'uso esterno
     */
    public function testProxyCall(string $url)
    {
        return $this->fetchPageWithProxy($url);
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
    public function syncPlayersData(array $playersData, int $teamId, int $seasonId, bool $dryRun = false, bool $rigidMapping = false): array
    {
        $results = [
            'total' => count($playersData),
            'matched' => 0,
            'updated' => 0,
            'noise' => 0,
            'log' => []
        ];

        // Recupero Roster Locale per Matching (ESTESO: include soft-deleted e record da recuperare)
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
                // 1. Match Esatto per ID (se già mappato)
                if ($lPlayer->fbref_id === $sId) {
                    $bestMatch = $lPlayer;
                    break;
                }

                // 2. Match via Helper di Progetto
                if ($this->namesAreSimilar($sName, $lPlayer->name)) {
                    $bestMatch = $lPlayer;
                    break;
                }
            }

            if ($bestMatch) {
                // LOGICA RIGID MAPPING (SST):
                // Se attivato, scartiamo il match se il giocatore locale non ha ID FBref (è un nuovo mapping) 
                // MA mancano gli ID di riferimento (Listone o API Football).
                if ($rigidMapping && empty($bestMatch->fbref_id)) {
                    if (empty($bestMatch->fanta_platform_id) && empty($bestMatch->api_football_data_id)) {
                        $results['noise']++;
                        $results['log'][] = "🚫 RIGID SKIP: '{$sName}' -> '{$bestMatch->name}' (Mancano ID di riferimento)";
                        continue;
                    }
                }

                $results['matched']++;
                $isNewMapping = ($bestMatch->fbref_id !== $sId || $bestMatch->fbref_url !== $sUrl);
                
                if ($isNewMapping) {
                    $results['updated']++;
                    if (!$dryRun) {
                        $bestMatch->update(['fbref_id' => $sId, 'fbref_url' => $sUrl]);
                        $results['log'][] = "✅ MATCH & UPDATE: '{$sName}' -> '{$bestMatch->name}'";
                    } else {
                        $results['log'][] = "🧪 [DRY-RUN] WOULD UPDATE: '{$sName}' -> '{$bestMatch->name}'";
                    }
                } else {
                    $results['log'][] = "ℹ️ ALREADY MAPPED: '{$sName}' -> '{$bestMatch->name}'";
                }
            } else {
                $results['noise']++;
                $results['log'][] = "❓ NO MATCH (Noise): '{$sName}'";
            }
        }

        return $results;
    }
}