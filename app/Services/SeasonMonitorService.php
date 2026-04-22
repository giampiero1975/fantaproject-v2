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
     * Inizializzazione completa della timeline delle stagioni.
     * Chiamata API unificata, ciclo cronologico e gestione is_current.
     */
    public function initializeFullTimeline(): array
    {
        $this->logEvent("[TIMELINE] Avvio inizializzazione timeline completa...");
        
        $currentYear = \App\Helpers\SeasonHelper::getCurrentSeason();
        $lookbackYears = \App\Helpers\SeasonHelper::getLookbackYears();
        $startYear = $currentYear - $lookbackYears;
        
        $apiKey = env('FOOTBALL_DATA_API_KEY');
        $results = [];
        
        try {
            // 1. Singola chiamata API per recuperare tutte le stagioni della Serie A
            $response = Http::withHeaders(['X-Auth-Token' => $apiKey])
                ->get('https://api.football-data.org/v4/competitions/SA');

            if ($response->failed()) {
                throw new \Exception("Chiamata API fallita: " . $response->status());
            }

            $apiData = $response->json();
            $apiSeasons = collect($apiData['seasons'] ?? []);
            
            // 2. Ciclo cronologico dal più vecchio al più recente
            for ($year = $startYear; $year <= $currentYear; $year++) {
                // Cerchiamo i dati nell'API per questo anno
                $apiSeason = $apiSeasons->first(fn($s) => substr($s['startDate'], 0, 4) == $year);
                
                $apiId = null;
                $startDate = "{$year}-08-20"; // Default legacy
                $endDate = ($year + 1) . "-05-25"; // Default legacy

                if ($apiSeason) {
                    $apiId = (int) $apiSeason['id'];
                    $startDate = $apiSeason['startDate'];
                    $endDate = $apiSeason['endDate'] ?? null;
                } elseif ($year < 2023) {
                    // ID convenzionale per anni legacy non presenti in API
                    $apiId = $year * 100;
                }

                if (!$apiId) {
                    $results[$year] = 'SKIPPED (NO API ID)';
                    continue;
                }

                $season = Season::updateOrCreate(
                    ['season_year' => $year],
                    [
                        'api_id' => $apiId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_current' => ($year === $currentYear),
                    ]
                );

                $results[$year] = $season->wasRecentlyCreated ? 'CREATED' : 'UPDATED';
            }

            $this->logEvent("[TIMELINE] Completata: " . json_encode($results));

            \App\Models\ImportLog::create([
                'import_type' => 'TIMELINE_INIT',
                'original_file_name' => 'SeasonMonitorService',
                'status' => 'successo',
                'details' => "Timeline inizializzata: " . json_encode($results),
                'rows_processed' => count($results),
            ]);

            return ['status' => 'success', 'results' => $results];

        } catch (\Exception $e) {
            $this->logEvent("[TIMELINE] Errore: " . $e->getMessage(), 'error');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
