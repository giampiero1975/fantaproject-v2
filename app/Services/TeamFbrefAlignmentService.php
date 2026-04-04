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
    protected bool $sessionStandingsScraped = false;


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
     * Esegue l'allineamento automatico delle squadre MANCANTI.
     * Se $year è null, tenta di allineare TUTTE le squadre attive in qualsiasi stagione che mancano di ID.
     */
    public function align(?int $year = null): array
    {
        // Rimuoviamo i limiti per gestire sessioni lunghe con ZenRows
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->proxyCalls = 0;
        $limit = env('PROXY_SESSION_LIMIT', 50);
        
        $seasonLabel = $year ?: 'Tutte le stagioni attive';
        $this->log("--- INIZIO SESSIONE ALLINEAMENTO DI MASSA ($seasonLabel) (Limite Proxy: $limit) ---");

        // CHECK BUDGET PREVENTIVO
        $proxy = app(ProxyManagerService::class)->getActiveProxy();
        if (!$proxy) {
            $this->log("🛑 ABORT: Nessun proxy attivo o budget esaurito.");
            return ['status' => 'error', 'message' => 'Budget esaurito'];
        }
        $this->log("✅ Proxy OK: {$proxy->name} ({$proxy->current_usage}/{$proxy->limit_monthly})");

        $scraper = app(FbrefScrapingService::class);
        
        // Passiamo l'anno specifica per ottenere la classifica corretta (es. 2025-2026)
        $standings = $scraper->scrapeSerieAStandings($year);

        if (empty($standings)) {
            $this->log("⚠️ Scraping classifica fallito. Procedo solo tramite fallback per ogni team...");
        }

        // Recupero squadre mancanti
        $query = Team::where(function($q) {
                $q->whereNull('fbref_id')->orWhere('fbref_id', '');
            });

        if ($year) {
            $season = \App\Models\Season::where('season_year', $year)->first();
            if ($season) {
                $query->whereHas('teamSeasons', fn($q) => $q->where('season_id', $season->id));
            }
        } else {
            // Tutte le squadre che sono state in Serie A (hanno almeno una team_season)
            $query->whereHas('teamSeasons');
        }

        $missingTeams = $query->get();

        if ($missingTeams->isEmpty()) {
            $this->log("✅ Nessuna squadra mancante di allineamento trovata.");
            return ['status' => 'success', 'matched' => 0, 'errors' => 0];
        }

        $this->log("Trovate {$missingTeams->count()} squadre da allineare.");

        $matchedCount = 0;
        $errors = 0;

        foreach ($missingTeams as $team) {
            if ($this->proxyCalls >= $limit) {
                $this->log("⚠️ SOGLIA PROXY RAGGIUNTA ($limit). Stop.");
                break;
            }

            $result = $this->alignTeam($team, $standings, $year);
            if ($result) $matchedCount++; else $errors++;
        }

        $this->log("--- SESSIONE COMPLETATA ({$matchedCount} match, {$errors} errori) ---");

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
    public function alignTeam(Team $team, array $standings = [], ?int $year = null): bool
    {
        // Protezione: non sovrascrivere se già presente
        if (!empty($team->fbref_id) && !empty($team->fbref_url)) {
            $this->log("ℹ️ Skipping '{$team->name}': ID già presente ({$team->fbref_id})");
            return true;
        }

        if (empty($standings) && !$this->sessionStandingsScraped) {
            $this->log("🔍 Tentativo di recupero classifica generale (Stagione $year)...");
            $scraper = app(FbrefScrapingService::class);
            $standings = $scraper->scrapeSerieAStandings($year);
            $this->sessionStandingsScraped = true;
        }

        $this->log("Targeting Team: {$team->name} (id: {$team->id})");
        
        $match = !empty($standings) ? $this->findMatchInStandings($team, $standings) : null;

        if ($match) {
            return $this->alignManual($team, $match['fbref_url']);
        } else {
            $this->log("⚠️ NESSUN MATCH in classifica per '{$team->name}' in questa stagione.");
            // DISABILITATA "RICERCA DIRETTA" FALLIBILE (No more search.fcgi robaccia)
        }

        return false;
    }


    /**
     * Forza l'allineamento manuale o validato tramite URL
     */
    public function alignManual(Team $team, string $url, ?string $fbrefId = null): bool
    {
        $this->log("Allineamento Manuale/Validato per '{$team->name}': $url" . ($fbrefId ? " (ID: $fbrefId)" : ""));
        
        $isValid = false;
        
        // Se abbiamo già un ID manuale, saltiamo la validazione esterna (trust human input)
        // per evitare errori di timeout proxy (504).
        if ($fbrefId && strlen($fbrefId) >= 8) {
            $this->log("✅ Usando ID Manuale fornito: $fbrefId (Validation skipped)");
            $isValid = true;
        } else {
            if (isset($this->sessionValidatedUrls[$url])) {
                $isValid = true;
            } else {
                $isValid = $this->validateUrl($url);
                if ($isValid) {
                    $this->sessionValidatedUrls[$url] = true;
                }
            }
        }

        if ($isValid) {
            $team->fbref_url = $url;
            
            if ($fbrefId) {
                $team->fbref_id = $fbrefId;
                $this->log("ID FBref impostato manualmente: {$fbrefId}");
            } else {
                // Estrazione ID dall'URL
                if (preg_match('/squads\/([a-f0-9]+)\//', $url, $matches)) {
                    $team->fbref_id = $matches[1];
                    $this->log("ID FBref estratto dall'URL: {$team->fbref_id}");
                }
            }
            
            $team->save();
            $this->log("✅ SUCCESS: '{$team->name}' -> FBref URL/ID salvato.");
            $this->sessionValidatedUrls[$url] = true;
            return true;
        }

        return false;
    }



    /**
     * Cerca il match migliore usando i Traits di sistema (L1-L3 in FindsTeam, L4 in FindsPlayerByName)
     */
    protected function findMatchInStandings(Team $team, array $standings): ?array
    {
        $teamNameNormal = strtolower(trim($team->name));
        $teamShortNormal = $team->short_name ? strtolower(trim($team->short_name)) : null;
        $teamOfficialNormal = $team->official_name ? strtolower(trim($team->official_name)) : null;

        foreach ($standings as $s) {
            $fbrefName = $s['fbref_name'];
            $fbrefNormal = strtolower(trim($fbrefName));
            
            // 1) Confronto diretto con short_name (Prioritario come indicato dall'utente)
            if ($teamShortNormal && ($teamShortNormal === $fbrefNormal)) {
                $this->log("✅ Match PERFETTO (Short Name): '{$team->short_name}' == '{$fbrefName}'");
                return $s;
            }

            // 2) Confronto diretto con Name o Official Name
            if ($teamNameNormal === $fbrefNormal || ($teamOfficialNormal && $teamOfficialNormal === $fbrefNormal)) {
                $this->log("✅ Match PERFETTO (Name): '{$team->name}' == '{$fbrefName}'");
                return $s;
            }

            // 3) Erosione fuzzy specifica per TEAM (Soglia 75%)
            // FORZIAMO IL MATCH SOLO SU SHORT_NAME come richiesto
            if ($team->short_name && $this->compareTeamNames($team->short_name, $fbrefName)) {
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
            
            // Usiamo il ProxyManager per costruire l'URL in modo dinamico
            $proxyUrl = $proxyManager->getProxyUrl($proxy, $url);

            // Timeout fissato a 40 secondi richiesto
            $response = Http::timeout(40)->withoutVerifying()->get($proxyUrl);

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
     * Algoritmo severo di matching per TEAM (Soglia 75%)
     */
    protected function compareTeamNames(string $name1, string $name2): bool
    {
        $clean1 = $this->normalizeTeamName($name1);
        $clean2 = $this->normalizeTeamName($name2);

        similar_text($clean1, $clean2, $percent);

        $isMatch = $percent >= 75;

        // Logging SEVERO per debug falsi positivi
        if ($percent > 20) {
            $status = $isMatch ? '✅ MATCH' : '❌ NO MATCH';
            $this->log("[CSI Match] $status: '$clean1' vs '$clean2' -> Score: " . round($percent, 2) . "%");
        }

        return $isMatch;
    }

    protected function normalizeTeamName(string $name): string
    {
        $name = strtolower(\Illuminate\Support\Str::ascii($name));
        // Rimuoviamo il "rumore" tipico dei nomi squadre
        $noise = ['ac ', 'fc ', 'as ', 'sc ', '1909', '1907', '1906', '1900', '1926', '1919', '1908', ' srl', ' spa', ' ssd'];
        $name = str_replace($noise, '', $name);
        $name = preg_replace('/[^a-z0-9]/', '', $name); // Solo alfanumerici

        return trim($name);
    }

    /**
     * Log nel canale dedicato
     */
    protected function log(string $message)
    {
        Log::channel('align_fbref')->debug($message);
    }
}
