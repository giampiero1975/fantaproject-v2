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

            $apiSeasonData = $response->json()['currentSeason'] ?? [];
            if (empty($apiSeasonData)) {
                throw new \Exception("Risposta API malformata: Nodo 'currentSeason' mancante.");
            }

            $apiSeasonId = (int) $apiSeasonData['id'];
            $localId = $currentDbSeason ? $currentDbSeason->id : 'NESSUNO';

            if ($isManual) {
                $this->logEvent("[MANUAL CHECK] ID DB: {$localId} vs ID API: {$apiSeasonId}");
                
                \App\Models\ImportLog::create([
                    'import_type' => 'SEASON_CHECK',
                    'original_file_name' => 'SeasonMonitorService',
                    'status' => ($localId !== 'NESSUNO' && $apiSeasonId === (int)$localId) ? 'ALLINEATO' : 'DISALLINEATO',
                    'details' => "[ID DB: {$localId} vs ID API: {$apiSeasonId}] - Check Manuale",
                ]);
            }

            // CASO 🟠 ARANCIONE: C'è una nuova stagione pronta all'hub (Discovery Positivo)
            if (!$currentDbSeason || $apiSeasonId !== (int)$currentDbSeason->id) {
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
}
