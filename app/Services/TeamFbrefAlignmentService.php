<?php

namespace App\Services;

use App\Models\Team;
use App\Traits\FindsTeam;
use App\Traits\FindsPlayerByName;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Services\ProxyManagerService;
use App\Helpers\SeasonHelper;

class TeamFbrefAlignmentService
{
    use FindsTeam, FindsPlayerByName;

    protected int $proxyCalls = 0;
    protected const MAX_PROXY_CALLS = 50;
    protected array $sessionValidatedUrls = [];

    /**
     * Mappa di emergenza rimossa in favore dei Trait di sistema (L1-L4)
     */

    public function __construct()
    {
        // La directory viene creata automaticamente dal logger se configurata bene,
        // ma manteniamo la sicurezza per la path se usata altrove.
        $logDir = storage_path('logs/Teams');
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }
    }

    /**
     * Esegue l'allineamento automatico delle squadre MANCANTI
     */
    public function align(): array
    {
        $this->proxyCalls = 0;
        $limit = env('PROXY_SESSION_LIMIT', 50);
        
        $this->log("--- INIZIO SESSIONE ALLINEAMENTO DI MASSA (Limite Proxy: $limit) ---");

        // CHECK BUDGET PREVENTIVO
        $proxy = app(ProxyManagerService::class)->getActiveProxy();
        if (!$proxy) {
            $this->log("🛑 ABORT: Nessun proxy attivo o budget esaurito.");
            return ['status' => 'error', 'message' => 'Budget esaurito'];
        }
        $this->log("✅ Proxy OK: {$proxy->name} ({$proxy->current_usage}/{$proxy->limit_monthly})");

        $scraper = app(FbrefScrapingService::class);
        $standings = $scraper->scrapeSerieAStandings();

        if (empty($standings)) {
            $this->log("🛑 Errore: Scraping classifica live fallito. Impossibile allineare FBref.");
            return ['status' => 'error', 'message' => 'Scraping fallito', 'matched' => 0, 'errors' => 1];
        }

        $targetSeason = SeasonHelper::getCurrentSeason();
        $currentSeasonModel = \App\Models\Season::where('season_year', $targetSeason)->first();
        $seasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        // Squadre attive nella stagione corrente che non hanno ancora l'allineamento FBref
        $missingTeams = Team::whereHas('teamSeasons', function($q) use ($seasonId) {
                $q->where('season_id', $seasonId);
            })
            ->where(function($query) {
                $query->whereNull('fbref_id')->orWhere('fbref_id', '');
            })
            ->get();

        if ($missingTeams->isEmpty()) {
            $this->log("✅ Nessuna squadra Serie A mancante di allineamento.");
            return ['status' => 'success', 'matched' => 0, 'errors' => 0];
        }

        $matchedCount = 0;
        $errors = 0;

        foreach ($missingTeams as $team) {
            if ($this->proxyCalls >= $limit) {
                $this->log("⚠️ SOGLIA PROXY RAGGIUNTA ($limit). Stop.");
                break;
            }

            $result = $this->alignTeam($team, $standings);
            if ($result) $matchedCount++; else $errors++;
        }

        return [
            'status' => 'success',
            'matched' => $matchedCount,
            'errors' => $errors,
            'proxy_calls' => $this->proxyCalls
        ];
    }

    /**
     * Allinea un singolo team specifico
     */
    public function alignTeam(Team $team, array $standings = []): bool
    {
        // Protezione: non sovrascrivere se già presente
        if (!empty($team->fbref_id) && !empty($team->fbref_url)) {
            $this->log("ℹ️ Skipping '{$team->name}': ID già presente ({$team->fbref_id})");
            return true;
        }

        if (empty($standings)) {
            $scraper = app(FbrefScrapingService::class);
            $standings = $scraper->scrapeSerieAStandings();
            if (empty($standings)) return false;
        }

        $this->log("Targeting Team: {$team->name} (id: {$team->id})");
        $match = $this->findMatchInStandings($team, $standings);

        if ($match) {
            $url = $match['fbref_url'];
            
            if (isset($this->sessionValidatedUrls[$url])) {
                $isValid = true;
            } else {
                $isValid = $this->validateUrl($url);
            }

            if ($isValid) {
                $team->fbref_url = $url;
                $team->fbref_id = $match['fbref_id'];
                $team->save();
                
                $this->log("✅ SUCCESS: '{$team->name}' -> FBref: '{$match['fbref_name']}'");
                $this->sessionValidatedUrls[$url] = true;
                return true;
            }
        } else {
            $this->log("⚠️ NESSUN MATCH per '{$team->name}'");
        }

        return false;
    }

    /**
     * Cerca il match migliore usando i Traits di sistema (L1-L3 in FindsTeam, L4 in FindsPlayerByName)
     */
    protected function findMatchInStandings(Team $team, array $standings): ?array
    {
        $this->preloadTeams();

        foreach ($standings as $s) {
            $fbrefName = $s['fbref_name'];
            
            // 1) Uso base del Trait FindsTeam (L1: short_name esatto, L2: name, L3: contains)
            $matchedTeamId = $this->findTeamIdByName($fbrefName);
            if ($matchedTeamId === $team->id) {
                return $s;
            }
            
            // 2) Erosione ibrida esplicita parziale tramite il trait (Names Are Similar)
            if ($team->short_name && $this->namesAreSimilar($team->short_name, $fbrefName)) {
                return $s;
            }
            if ($team->name && $this->namesAreSimilar($team->name, $fbrefName)) {
                return $s;
            }
        }

        return null;
    }

    protected function validateUrl(string $url): bool
    {
        $this->proxyCalls++;
        $proxyManager = app(ProxyManagerService::class);
        $proxy = $proxyManager->getActiveProxy();
        
        if (!$proxy) {
            $this->log("⚠️ Validazione fallita: Nessun proxy attivo.");
            return false;
        }

        try {
            $this->log("Proxy Call #{$this->proxyCalls} ({$proxy->name}): Validazione $url");
            
            // Usiamo il provider per costruire l'URL del proxy
            $providerClass = 'App\\Services\\ProxyProviders\\' . ($proxy->name === 'ScraperAPI' ? 'ScraperApiProvider' : 'ScraperApiProvider');
            $proxyUrl = app($providerClass)->getProxyUrl($proxy, $url);

            $response = Http::timeout(10)->get($proxyUrl);
            $status = $response->status();
            
            $this->log("Status: [$status] | Proxy: {$proxy->name}");

            if ($status === 404) {
                $this->log("[FATAL] URL 404 riscontrato per: $url. Discovery fallita.");
                return false;
            }

            return $status === 200 || $status === 403;
        } catch (\Exception $e) {
            $this->log("Errore connessione via proxy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Il controllo budget è ora gestito dal ProxyManagerService.
     */

    /**
     * Log nel canale dedicato
     */
    protected function log(string $message)
    {
        Log::channel('align_fbref')->debug($message);
    }
}
