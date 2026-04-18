<?php

namespace App\Console\Commands\Extraction;

use App\Models\Player;
use App\Models\Team;
use App\Models\PlayerFbrefStat;
use App\Services\FbrefScrapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Traits\ManagesFbrefScraping;
use Symfony\Component\DomCrawler\Crawler;

class FbrefScrapeTeam extends Command
{
    use ManagesFbrefScraping;

    /**
     * La firma del comando.
     * @var string
     */
    protected $signature = 'fbref:scrape-team {url} {--team_id=} {--season=} {--league=Serie A} {--force} {--render}';

    /**
     * Descrizione del comando.
     * @var string
     */
    protected $description = 'Esegue lo scraping FBref di una squadra con logica chirurgica e salvataggio a 3 layer.';

    protected FbrefScrapingService $scraper;

    public function __construct(FbrefScrapingService $scraper)
    {
        parent::__construct();
        $this->scraper = $scraper;
    }

    /**
     * Esecuzione del comando.
     */
    public function handle()
    {
        $url = trim($this->argument('url')); // Sanificazione spazi
        $teamId = $this->option('team_id');
        $seasonYear = $this->option('season');
        $league = $this->option('league');
        $force  = $this->option('force');
        $render = $this->option('render');

        if (!$teamId || !$seasonYear) {
            $this->error('ERRORE: --team_id e --season sono obbligatori.');
            return Command::FAILURE;
        }

        $team = Team::find($teamId);
        if (!$team) {
            $this->error("ERRORE: Team ID {$teamId} non trovato.");
            return Command::FAILURE;
        }

        // Risoluzione Season ID (Normalizzazione v2.0)
        $season = \App\Models\Season::where('season_year', $seasonYear)->first();
        if (!$season) {
            $this->error("ERRORE: Stagione '{$seasonYear}' non configurata nel database.");
            return Command::FAILURE;
        }

        $this->info("🚀 AVVIO SCRAPING CHIRURGICO v2.0: {$team->name} ({$seasonYear})");
        $this->line("🔗 URL: {$url}");
        if ($render) $this->warn("⚡ Modalità JS Rendering ATTIVA (Premium)");

        // --- 1. DOWNLOAD HTML (Via Proxy) ---
        try {
            $proxyResponse = $this->scraper->testProxyCall($url, $render); 
            $rawHtml = $proxyResponse->html();

            // Svelamento commenti (Fase 2 - Regex via Trait)
            $this->info("🔓 Svelamento tabelle nascoste...");
            $uncommentedHtml = $this->scraper->uncommentHiddenHtml($rawHtml);
        } catch (\Exception $e) {
            $this->error("❌ FALLIMENTO DOWNLOAD: " . $e->getMessage());
            return Command::FAILURE;
        }

        // --- 2. ESTRAZIONE DATI ---
        $this->info("📊 Estrazione tabelle in corso...");
        $scrapedData = $this->scraper->parseDataFromHtml($uncommentedHtml, 'team_player_stats');

        if (empty($scrapedData)) {
            $this->error("❌ Nessun dato estratto. Controlla lo schema fbref_schemas.");
            return Command::FAILURE;
        }

        // Aggregazione per calciatore
        $playersAggregated = $this->aggregatePlayerData($scrapedData);
        $this->info("👥 Trovati " . count($playersAggregated) . " calciatori nel dataset.");

        // --- FASE 4: SCATOLA NERA (Logging Artifacts) ---
        $this->saveArtifacts($rawHtml, $url, $teamId, $seasonYear, $playersAggregated);

        // --- 3. PROCESSING E PERSISTENZA ---
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'not_found' => 0];
        $bar = $this->output->createProgressBar(count($playersAggregated));

        foreach ($playersAggregated as $fbrefName => $data) {
            $fbrefId = $data['fbref_id_extracted'] ?? null;
            
            // 🔍 IDEMPOTENZA
            $player = $this->findLocalPlayer($fbrefName, $fbrefId, $teamId);

            if (!$player) {
                $stats['not_found']++;
                Log::warning("[fbref:scrape-team] Giocatore non trovato: {$fbrefName} (ID: {$fbrefId})", ['team' => $team->name]);
                $bar->advance();
                continue;
            }

            // Controllo esistenza record per salto (Idempotenza Normalizzata + Trashed Support)
            $existing = PlayerFbrefStat::withTrashed()->where([
                'player_id' => $player->id,
                'team_id' => $teamId,
                'season_id' => $season->id,
            ])->first();

            if ($existing && !$force) {
                $stats['skipped']++;
                $bar->advance();
                continue;
            }

            // --- FASE 3 & 5: PERSISTENZA E NORMALIZZAZIONE v2.0 ---
            // Solo i Magnifici 7 finiscono nelle colonne fisiche. Il resto in data_team.
            $dataToSave = $this->prepareLayeredData($data);
            $dataToSave['season_id'] = $season->id;
            $dataToSave['deleted_at'] = null; // Forza il ripristino se era cancellato

            if ($existing) {
                $existing->update($dataToSave);
                $stats['updated']++;
            } else {
                PlayerFbrefStat::create(array_merge([
                    'player_id' => $player->id,
                    'team_id' => $teamId,
                    'season_id' => $season->id
                ], $dataToSave));
                $stats['created']++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // --- SUMMARY ---
        $this->info("✅ OPERAZIONE COMPLETATA (Architettura v2.0 - Normalizzata)");
        $this->table(['Stato', 'Conteggio'], [
            ['Creati', $stats['created']],
            ['Aggiornati', $stats['updated']],
            ['Saltati (Idempotenza)', $stats['skipped']],
            ['Match Falliti', $stats['not_found']],
        ]);

        Log::info("[fbref:scrape-team] Sync v2.0 (Deep JSON) completato per {$team->name} Stagione ID: {$season->id}", $stats);
        
        return Command::SUCCESS;
    }

    /**
     * Aggrega le statistiche di diverse tabelle (Standard, Shooting, Passing, ecc.)
     * usando fbref_id_extracted come chiave primaria di merge.
     *
     * STRATEGIA NON-DISTRUTTIVA:
     * - stats_standard arriva per primo: popola le colonne base (gol, assist, minuti...)
     * - stats_shooting arriva dopo: aggiunge xg, npxg e shot stats SENZA sovrascrivere i dati base
     * - Risultato: un record completo per ogni calciatore con dati da tutte le tabelle
     */
    private function aggregatePlayerData(array $scrapedData): array
    {
        $aggregated = [];

        foreach ($scrapedData as $tableKey => $rows) {
            foreach ($rows as $row) {
                // Chiave primaria: ID FBref univoco. Fallback sicuro: nome giocatore.
                $key = $row['fbref_id_extracted'] ?? ($row['Player'] ?? null);
                if (!$key) continue;

                if (!isset($aggregated[$key])) {
                    // Prima apparizione del calciatore: salviamo tutto
                    $aggregated[$key] = $row;
                } else {
                    // Merge NON-DISTRUTTIVO: aggiungiamo solo i campi assenti (es. xg da Shooting)
                    // I campi già popolati (es. goals da Standard) NON vengono sovrascritti
                    foreach ($row as $field => $value) {
                        if (!array_key_exists($field, $aggregated[$key])
                            || $aggregated[$key][$field] === ''
                            || $aggregated[$key][$field] === null) {
                            $aggregated[$key][$field] = $value;
                        }
                    }
                }
            }
        }

        return $aggregated;
    }


    /**
     * Trova il calciatore locale con priorità 1:1 sull'ID.
     */
    private function findLocalPlayer(string $name, ?string $fbrefId, int $teamId): ?Player
    {
        if ($fbrefId) {
            $p = Player::where('fbref_id', $fbrefId)->first();
            if ($p) return $p;
        }
        return $this->scraper->findPlayer(['name' => $name], Team::find($teamId));
    }

    /**
     * Prepara il salvataggio a 3 Layer (Physic / Deep JSON / Raw HTML).
     * MAPPATURA RIGIDA LAYER 1.
     */
    private function prepareLayeredData(array $data): array
    {
        $layered = [];
        
        // I "Magnifici 7" + Relazioni Fondamentali
        $layer1Columns = [
            'games', 'games_starts', 'minutes', 'minutes_90s',
            'goals', 'assists', 'cards_yellow', 'cards_red', 'xg', 'npxg'
        ];
        
        foreach ($layer1Columns as $col) {
            if (isset($data[$col])) {
                $cleanValue = is_string($data[$col]) ? str_replace(',', '', $data[$col]) : $data[$col];
                $layered[$col] = ($cleanValue === '' || $cleanValue === null) ? null : $cleanValue;
            }
        }

        // Deep Storage: Tutto il resto dell'HTML parsato finisce qui
        $layered['data_team'] = $data;

        return $layered;
    }

    /**
     * Salva HTML e JSON per tracciabilità Black Box in storage/logs/scraping.
     */
    private function saveArtifacts(string $html, string $url, int $teamId, string $seasonYear, ?array $json = null): void
    {
        $timestamp = now()->format('Ymd_His');
        $fileName = "team_{$teamId}_{$timestamp}";
        $baseDir = storage_path("logs/scraping/fbref");

        // HTML Save
        $htmlDir = "{$baseDir}/html/{$seasonYear}";
        if (!File::isDirectory($htmlDir)) File::makeDirectory($htmlDir, 0755, true);
        File::put("{$htmlDir}/{$fileName}.html", $html);

        // JSON Save
        if ($json) {
            $jsonDir = "{$baseDir}/json/{$seasonYear}";
            if (!File::isDirectory($jsonDir)) File::makeDirectory($jsonDir, 0755, true);
            File::put("{$jsonDir}/{$fileName}.json", json_encode($json, JSON_PRETTY_PRINT));
        }

        $this->info("💾 Black Box salvata in: storage/logs/scraping/fbref/.../{$fileName}");
    }
}
