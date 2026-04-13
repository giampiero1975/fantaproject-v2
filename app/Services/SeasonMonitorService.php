<?php

namespace App\Services;

use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeasonMonitorService
{
    public const STATUS_OK = 'OK';              // Verde
    public const STATUS_WAITING = 'WAITING';    // Giallo
    public const STATUS_UPDATE = 'UPDATE';      // Arancio
    public const STATUS_IDLE_OFF_SEASON = 'IDLE_OFF_SEASON'; // Letargo Estivo (Grigio)
    public const STATUS_ERROR = 'ERROR';        // Errore API
    
    // Status Locali di Sincronizzazione Dati
    public const LOCAL_STATUS_SYNCED = 'SYNCED';        // Completa (20 team)
    public const LOCAL_STATUS_INCOMPLETE = 'INCOMPLETE'; // Incompleta (< 20 team)
    public const LOCAL_STATUS_EMPTY = 'EMPTY';           // Vuota (0 team)

    /**
     * Helper per scrivere log mirati nel file della Gestione Stagione
     */
    protected function logEvent(string $message, string $level = 'info')
    {
        $logPath = storage_path('logs/GestioneStagioni');
        if (!\Illuminate\Support\Facades\File::exists($logPath)) {
            \Illuminate\Support\Facades\File::makeDirectory($logPath, 0755, true);
        }
        
        $logger = \Illuminate\Support\Facades\Log::build([
            'driver' => 'single',
            'path' => $logPath . '/stagioni.log',
        ]);

        $logger->$level($message);
        \Illuminate\Support\Facades\Log::$level("[SeasonMonitor] " . $message);
    }

    /**
     * Determina lo stato attuale della stagione con controlli intelligenti di letargo.
     * Usato prettamente per il badge passivo nella Dashboard (nessuna chiamata API d'estate).
     */
    public function getStatus(): array
    {
        $currentDbSeason = Season::where('is_current', true)->first();
        $now = Carbon::now();

        // LOGICA BLACKOUT AUTOMATICO (LETARGO ESTIVO)
        if ($currentDbSeason && $currentDbSeason->end_date) {
            $endDate = Carbon::parse($currentDbSeason->end_date);
            $augustFirst = Carbon::create($endDate->year, 8, 1)->startOfDay();
            
            // Se siamo dopo la fine del campionato E prima del 1° Agosto dell'anno in cui è finito
            if ($now->isAfter($endDate) && $now->isBefore($augustFirst)) {
                $this->logEvent("[AUTO CHECK] Blackout Estivo Attivato. Nessuna chiamata API per evitare consumo rate-limit.");
                return [
                    'status' => self::STATUS_IDLE_OFF_SEASON,
                    'label' => 'PAUSA ESTIVA',
                    'description' => "Campionato terminato il {$endDate->format('d/m/Y')}. Il sistema è in letargo di default (Nessuna chiamata automatica).",
                    'color' => 'gray',
                    'icon' => 'heroicon-o-moon',
                ];
            }
        }

        // Se non siamo in letargo, procediamo alla chiamata effettiva
        return $this->checkNow($currentDbSeason, $now);
    }

    /**
     * Effettua una chiamata API immediata, ignorando i limiti del letargo stagionale.
     * Metodo ideale per l'Area Impostazioni.
     */
    public function checkNow(?Season $currentDbSeason = null, ?Carbon $now = null, bool $isManual = false): array
    {
        $currentDbSeason = $currentDbSeason ?? Season::where('is_current', true)->first();
        $now = $now ?? Carbon::now();
        $apiKey = env('FOOTBALL_DATA_API_KEY');

        try {
            // 1. Chiamiamo l'API ufficiale di Discovery
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey
            ])->get('https://api.football-data.org/v4/competitions/SA');

            if ($response->failed()) {
                throw new \Exception("Chiamata all'API fallita. Status Code: " . $response->status());
            }

            $apiRawData = $response->json();
            
            // 🔥 FIX LEAGUE COUNTRY CODE: ITA -> IT
            $this->syncLeagueMetadata($apiRawData);

            $apiSeasonData = $apiRawData['currentSeason'] ?? [];
            if (empty($apiSeasonData)) {
                throw new \Exception("Risposta API malformata: Nodo 'currentSeason' mancante.");
            }

            $apiSeasonId = (int) $apiSeasonData['id'];
            $localApiId = $currentDbSeason ? $currentDbSeason->api_id : 'NESSUNO';

            if ($isManual) {
                $this->logEvent("[MANUAL CHECK] API_ID DB: {$localApiId} vs API_ID LIVE: {$apiSeasonId}");
                
                \App\Models\ImportLog::create([
                    'import_type' => 'SEASON_CHECK',
                    'original_file_name' => 'SeasonMonitorService',
                    'status' => ($localApiId !== 'NESSUNO' && $apiSeasonId === (int)$localApiId) ? 'ALLINEATO' : 'DISALLINEATO',
                    'details' => "[API_ID DB: {$localApiId} vs API_ID LIVE: {$apiSeasonId}] - Check Manuale",
                    'rows_processed' => 1,
                ]);
            }

            // CASO 🟠 ARANCIONE: C'è una nuova stagione pronta all'hub (Discovery Positivo)
            if (!$currentDbSeason || $apiSeasonId !== (int)$currentDbSeason->api_id) {
                return [
                    'status' => self::STATUS_UPDATE,
                    'label' => 'DA AGGIORNARE',
                    'description' => "Rilevata Nuova Stagione con Start: {$apiSeasonData['startDate']}.",
                    'color' => 'warning',
                    'icon' => 'heroicon-o-arrow-path',
                    'api_id' => $apiSeasonId,
                    'api_data' => $apiSeasonData
                ];
            }

            // CASO 🟡 GIALLO: Stagione finita in date locali, in attesa del cambio anno sull'API
            if ($currentDbSeason && $currentDbSeason->end_date) {
                $endDate = Carbon::parse($currentDbSeason->end_date);
                if ($now->isAfter($endDate)) {
                    return [
                        'status' => self::STATUS_WAITING,
                        'label' => 'IN ATTESA',
                        'description' => "Campionato concluso in data {$endDate->format('d/m/Y')}. Attesa flip da parte del provider API.",
                        'color' => 'warning',
                        'icon' => 'heroicon-o-clock',
                        'api_id' => $apiSeasonId,
                        'api_data' => $apiSeasonData
                    ];
                }
            }

            // CASO 🟢 VERDE: Tutto allineato e campionato in pieno corso
            return [
                'status' => self::STATUS_OK,
                'label' => 'IN CORSO',
                'description' => "Stagione " . ($currentDbSeason ? $currentDbSeason->season_year : 'Attiva') . " allineata e operativa.",
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
                'api_id' => $apiSeasonId,
                'api_data' => $apiSeasonData
            ];

        } catch (\Exception $e) {
            Log::error("SeasonMonitorService checkNow Error: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'label' => 'ERRORE API',
                'description' => $e->getMessage(),
                'color' => 'danger',
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }
    }

    /**
     * Sincronizza i metadati della Lega (Serie A) mappando correttamente i codici nazione.
     */
    protected function syncLeagueMetadata(array $apiData): void
    {
        $leagueId = (int) ($apiData['id'] ?? 2019);
        $areaCode = $apiData['area']['code'] ?? 'ITA';
        
        // Nessun mapping: usiamo il valore puro dall'API (es. ITA)
        $countryCode = $areaCode;

        \App\Models\League::updateOrCreate(
            ['api_id' => $leagueId],
            [
                'name' => $apiData['name'] ?? 'Serie A',
                'country_code' => $countryCode,
            ]
        );

        $this->logEvent("[LEAGUE SYNC] Lega {$leagueId} aggiornata: code {$countryCode}");
    }

    /**
     * Valuta l'effettiva presenza di dati (squadre) per una stagione locale.
     */
    public function getLocalStatus(Season $season): array
    {
        $teamsCount = $season->teams_count ?? $season->teams()->count();

        if ($teamsCount >= 20) {
            return [
                'status' => self::LOCAL_STATUS_SYNCED,
                'label' => 'COMPLETA',
                'description' => "Tutte le 20 squadre sono sincronizzate.",
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        }

        if ($teamsCount > 0) {
            return [
                'status' => self::LOCAL_STATUS_INCOMPLETE,
                'label' => 'INCOMPLETA',
                'description' => "Rilevate solo {$teamsCount} squadre su 20.",
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        return [
            'status' => self::LOCAL_STATUS_EMPTY,
            'label' => 'DA SCARICARE',
            'description' => "Nessun dato presente per questa stagione.",
            'color' => 'danger',
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    /**
     * Calcola lo stato di salute dello storico a 4 anni (Lookback).
     * Ritorna i 4 anni precedenti la stagione corrente e la loro completezza.
     */
    public function getHistoricalLookback(?int $count = null): array
    {
        $count = (int) ($count ?? config('football.lookback_years', 4));
        $currentSeason = Season::where('is_current', true)->first();
        if (!$currentSeason) {
            return [
                'is_ready'    => false,
                'ready_count' => 0,
                'exists_count'=> 0,
                'target_count'=> $count,
                'years'       => [],
                'status'      => 'MISSING_CURRENT',
                'current_year'=> null
            ];
        }

        $currentYear = $currentSeason->season_year;
        $targetYears = [];
        for ($i = 0; $i <= $count; $i++) {
            $targetYears[] = $currentYear - $i;
        }

        $yearsData = [];
        $readyCount = 0;

        foreach ($targetYears as $year) {
            $season = Season::where('season_year', $year)
                ->withCount(['teams as teams_count' => function ($query) {
                    $query->where('team_season.league_id', 1);
                }])->first();
            $teamsCount = $season ? $season->teams_count : 0;
            $isComplete = $teamsCount >= 20;

            if ($isComplete) {
                $readyCount++;
            }

            $yearsData[] = [
                'year'             => $year,
                'exists'           => (bool)$season,
                'teams_count'      => $teamsCount,
                'is_complete'      => $isComplete,
                'is_api_supported' => $year >= 2023,
                'status'           => $this->getLocalStatusForYear($season, $teamsCount),
            ];
        }

        $existsCount = collect($yearsData)->where('exists', true)->count();

        return [
            'is_ready'    => $readyCount === $count,
            'exists_count'=> $existsCount,
            'ready_count' => $readyCount,
            'target_count'=> $count,
            'years'       => $yearsData,
            'current_year'=> $currentYear
        ];
    }

    /**
     * Helper interno per mappare lo stato di un anno specifico.
     */
    protected function getLocalStatusForYear(?Season $season, int $count): array
    {
        if (!$season) {
            return [
                'label' => 'MANCANTE',
                'color' => 'danger',
                'icon'  => 'heroicon-o-x-circle'
            ];
        }

        if ($count >= 20) {
            return [
                'label' => 'COMPLETA',
                'color' => 'success',
                'icon'  => 'heroicon-o-check-circle'
            ];
        }

        return [
            'label' => 'INCOMPLETA',
            'color' => 'warning',
            'icon'  => 'heroicon-o-exclamation-triangle'
        ];
    }

    /**
     * Inizializzazione automatica dello storico (4 anni).
     * < 2023: ID convenzionale year*100.
     * >= 2023: Trigger del comando sync per far valorizzare l'ID dall'API.
     */
    public function bootstrapHistory(): array
    {
        $currentSeason = Season::where('is_current', true)->first();
        if (!$currentSeason) {
            return ['status' => 'error', 'message' => 'Nessuna stagione corrente attiva.'];
        }

        $currentYear = $currentSeason->season_year;
        $lookbackYears = config('football.lookback_years', 4);
        $results = [];
        $apiKey = env('FOOTBALL_DATA_API_KEY');

        $rowsProcessed = 0;
        $rowsCreated = 0;
        $rowsUpdated = 0;

        for ($i = 1; $i <= $lookbackYears; $i++) {
            $rowsProcessed++;
            $year = $currentYear - $i;
            $season = Season::where('season_year', $year)->first();

            if ($season) {
                $results[$year] = 'EXISTS';
                $rowsUpdated++;
                continue;
            }

            if ($year < 2023) {
                // Anni Legacy: API_ID convenzionale
                Season::create([
                    'api_id' => $year * 100,
                    'season_year' => $year,
                    'start_date' => "{$year}-08-20",
                    'end_date' => ($year + 1) . "-05-25",
                    'is_current' => false,
                ]);
                $results[$year] = 'CREATED (LEGACY)';
                $rowsCreated++;
            } else {
                // Anni API: Recupero solo date (Scatola Stagione) senza teams
                $response = Http::withHeaders(['X-Auth-Token' => $apiKey])
                    ->get("https://api.football-data.org/v4/competitions/SA/teams", ['season' => $year]);

                if ($response->successful()) {
                    $apiData = $response->json();
                    $apiSeason = $apiData['season'] ?? null;
                    if ($apiSeason && isset($apiSeason['id'])) {
                        Season::create([
                            'api_id' => (int) $apiSeason['id'],
                            'season_year' => $year,
                            'start_date' => $apiSeason['startDate'],
                            'end_date' => $apiSeason['endDate'] ?? null,
                            'is_current' => false,
                        ]);
                        $results[$year] = 'CREATED (BOX ONLY)';
                        $rowsCreated++;
                    } else {
                        $results[$year] = 'ERROR (MALFORMED API)';
                    }
                } else {
                    $results[$year] = 'ERROR (API FAIL)';
                }
            }
        }

        \App\Models\ImportLog::create([
            'import_type' => 'LOOKBACK_BOOTSTRAP',
            'original_file_name' => 'SeasonMonitorService',
            'status' => 'successo',
            'details' => "Inizializzazione storico completata: " . json_encode($results),
            'rows_processed' => $rowsProcessed,
            'rows_created' => $rowsCreated,
            'rows_updated' => $rowsUpdated,
        ]);

        return ['status' => 'success', 'results' => $results];
    }
}
