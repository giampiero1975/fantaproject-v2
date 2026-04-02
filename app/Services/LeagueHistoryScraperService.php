<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamHistoricalStanding;
use App\Helpers\SeasonHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\File;
use App\Services\ProxyManagerService;

class LeagueHistoryScraperService
{
    use \App\Traits\FindsTeam;

    protected string $historyUrl = 'https://fbref.com/en/comps/11/history/Serie-A-Seasons';
    protected string $logChannel = 'history_import';

    /**
     * Esegue lo scraping dello storico per le stagioni definite nel SeasonHelper (5 anni).
     */
    public function scrapeHistory(): array
    {
        $this->log("--- INIZIO IMPORT STORICO SERIE A (ProxyManager) ---");

        // 1. Check Proxy Budget
        $proxy = app(ProxyManagerService::class)->getActiveProxy();
        if (!$proxy) {
            $this->log("🛑 ABORT: Nessun proxy attivo o budget esaurito.");
            return ['status' => 'error', 'message' => 'Budget insufficiente'];
        }

        // 2. Recupero Stagioni Target
        $targetSeasons = SeasonHelper::getLookbackSeasons(5);
        $this->log("🔍 Stagioni da analizzare: " . implode(', ', array_keys($targetSeasons)));

        // 3. Scarico Pagina History Principale
        $html = $this->getHtmlWithProxy($this->historyUrl);
        if (!$html) {
            $this->log("❌ Impossibile scaricare la pagina history.");
            return ['status' => 'error', 'message' => 'Download fallito'];
        }

        $crawler = new Crawler($html);
        $rows = $crawler->filter('table#seasons tbody tr');
        $importStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $rows->each(function (Crawler $row) use ($targetSeasons, &$importStats) {
            $th = $row->filter('th[data-stat="year_id"] a');
            if ($th->count() === 0) return;

            $seasonLabel = $th->text(); // Es: "2024-2025"
            $seasonYear = (int) substr($seasonLabel, 0, 4);
            $seasonUrl = "https://fbref.com" . $th->attr('href');

            if (!isset($targetSeasons[$seasonYear])) {
                return; // Fuori dal periodo di interesse
            }

            $this->log("📂 Analisi Stagione: $seasonLabel ($seasonYear)");
            $stats = $this->scrapeSeasonStandings($seasonUrl, $seasonYear, false); // Default: don't save standings
            
            $importStats['created'] += $stats['created'];
            $importStats['updated'] += $stats['updated'];
            $importStats['skipped'] += $stats['skipped'];
        });

        $this->log("✅ Import completato. Creati: {$importStats['created']}, Aggiornati: {$importStats['updated']}");
        return ['status' => 'success', 'stats' => $importStats];
    }

    /**
     * Scrapes the standings for a specific season URL.
     */
    public function scrapeSeason(int $year): array
    {
        $this->log("🔍 Avvio scraping stagionale mirato per l'anno: $year");
        
        $proxy = app(ProxyManagerService::class)->getActiveProxy();
        if (!$proxy) return ['status' => 'error', 'message' => 'Proxy non disponibile'];

        $nextYear = $year + 1;
        $url = "https://fbref.com/en/comps/11/{$year}-{$nextYear}/{$year}-{$nextYear}-Serie-A-Stats";
        
        $stats = $this->scrapeSeasonStandings($url, $year, false); // Sempre false quando chiamato dall'azione Teams
        
        $total = $stats['created'] + $stats['updated'];
        $this->log("✅ Fine scraping $year. Importati/Aggiornati: $total");

        return ['status' => 'success', 'stats' => $stats];
    }

    /**
     * Scrapes the standings for a specific season URL.
     */
    protected function scrapeSeasonStandings(string $url, int $year, bool $saveStandings = false): array
    {
        $html = $this->getHtmlWithProxy($url);
        if (!$html) {
            $this->log("  ⚠️ Fallito download stagione $year.");
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $crawler = new Crawler($html);
        $table = $crawler->filter('table[id^="results"][id$="_overall"]');
        
        if ($table->count() === 0) {
            $table = $crawler->filter('table.stats_table')->first();
        }

        if ($table->count() === 0) {
            $this->log("  ⚠️ Tabella classifica non trovata per $year.");
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $rows = $table->filter('tbody tr');

        $rows->each(function (Crawler $row) use ($year, &$stats) {
            $teamLink = $row->filter('td[data-stat="team"] a');
            if ($teamLink->count() === 0) return;

            $teamName = $teamLink->text();
            $teamUrl = $teamLink->attr('href');
            
            if (preg_match('/\/squads\/([^\/]+)\//', $teamUrl, $matches)) {
                $fbrefId = $matches[1];
            } else {
                return;
            }

            // Ricerca intelligente della squadra (esatto -> short_name -> contains) via Trait
            $team = Team::where('fbref_id', $fbrefId)->first();
            
            if (!$team) {
                $teamId = $this->findTeamIdByName($teamName);
                if ($teamId) {
                    $team = Team::find($teamId);
                }
            }
            
            if (!$team) {
                $team = Team::create([
                    'name' => $teamName,
                    'short_name' => $teamName,
                    'fbref_id' => $fbrefId,
                    'fbref_url' => "https://fbref.com$teamUrl",
                ]);
            } elseif (empty($team->fbref_id) || empty($team->short_name)) {
                // Colleghiamo la squadra esistente (creata via API) all'ID FBref per i futuri lookup
                // E assicuriamoci di popolare lo short_name se manca
                $team->update([
                    'fbref_id' => $team->fbref_id ?: $fbrefId,
                    'fbref_url' => $team->fbref_url ?: "https://fbref.com$teamUrl",
                    'short_name' => $team->short_name ?: $teamName,
                ]);
            }

            // Assicuriamoci che esista il record in team_season per collegare la squadra alla stagione
            $seasonModel = \App\Models\Season::where('season_year', $year)->first();
            if ($seasonModel) {
                \App\Models\TeamSeason::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'season_id' => $seasonModel->id,
                    ],
                    [
                        'league_id' => \App\Models\League::where('api_id', 2019)->first()?->id ?? 1,
                        'is_active' => true,
                    ]
                );
            }

            $safeGet = function($selector, $type = 'text') use ($row) {
                $node = $row->filter($selector);
                if ($node->count() === 0) return 0;
                return $type === 'text' ? $node->text() : $node->attr($type);
            };

            $rank = (int) $safeGet('td[data-stat="rank"]');
            if ($rank === 0) $rank = (int) $safeGet('th[data-stat="rank"]');

            if ($saveStandings) {
                $data = [
                    'team_id' => $team->id,
                    'season_year' => $year,
                    'league_name' => 'Serie A',
                    'position' => $rank,
                    'played_games' => (int) $safeGet('td[data-stat="games"]'),
                    'won' => (int) $safeGet('td[data-stat="wins"]'),
                    'draw' => (int) $safeGet('td[data-stat="draws"]'),
                    'lost' => (int) $safeGet('td[data-stat="losses"]'),
                    'goals_for' => (int) $safeGet('td[data-stat="goals_for"]'),
                    'goals_against' => (int) $safeGet('td[data-stat="goals_against"]'),
                    'goal_difference' => (int) $safeGet('td[data-stat="goal_diff"]'),
                    'points' => (int) $safeGet('td[data-stat="points"]'),
                    'data_source' => 'fbref',
                ];

                $standing = TeamHistoricalStanding::updateOrCreate(
                    ['team_id' => $team->id, 'season_year' => $year, 'league_name' => 'Serie A'],
                    $data
                );

                if ($standing->wasRecentlyCreated) $stats['created']++; else $stats['updated']++;
            }
        });

        return $stats;
    }

    /**
     * Esegue la chiamata tramite ProxyManager con tracciamento diagnostico.
     */
    protected function getHtmlWithProxy(string $url): ?string
    {
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();
        if (!$proxy) {
            $this->log("🛑 [DIAG] Nessun proxy disponibile.");
            return null;
        }

        try {
            $proxyUrl = $proxyManager->getProxyUrl($proxy, $url);
            
            // Mascheriamo la chiave per il log ma verifichiamo la struttura
            $maskedUrl = preg_replace('/api_key=[^&]+/', 'api_key=********', $proxyUrl);
            $this->log("📡 [REQ START] Provider: {$proxy->name} | Target: $url");
            $this->log("🔗 [PROXY URL] $maskedUrl");

            $startTime = microtime(true);
            $response = Http::timeout(120)->get($proxyUrl);
            $duration = round(microtime(true) - $startTime, 2);

            // Sincronizzazione automatica del saldo dopo ogni chiamata per tracciabilità crediti
            try {
                $proxyManager->syncBalance($proxy);
            } catch (\Exception $e) {
                $this->log("⚠️ [SYNC ERROR] Impossibile aggiornare saldo proxy: " . $e->getMessage());
            }

            $this->log("📥 [RES END] Status: " . $response->status() . " | Durata: {$duration}s | Size: " . strlen($response->body()) . " bytes");

            // Salviamo un dump della risposta per l'analisi visiva
            $dumpPath = storage_path('logs/Imports/debug_fbref.html');
            File::put($dumpPath, $response->body());
            $this->log("💾 [DUMP] Salvato in: $dumpPath");

            if ($response->successful()) {
                return $response->body();
            } else {
                $this->log("❌ [RES ERROR] " . $response->status() . " - " . substr($response->body(), 0, 200));
            }
        } catch (\Exception $e) {
            $this->log("🔥 [EXCEPTION] " . $e->getMessage());
        }

        return null;
    }

    protected function log(string $message)
    {
        Log::channel($this->logChannel)->debug($message);
    }
}
