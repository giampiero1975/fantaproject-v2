<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PlayerStatsApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiKeyName;
    protected string $activeProvider;
    protected array $apiConfig;
    
    public function __construct()
    {
        $this->activeProvider = 'football_data_org';
        $configPath = "services.player_stats_api.providers.{$this->activeProvider}";
        $this->apiConfig = config($configPath);
        
        if (is_null($this->apiConfig) || empty($this->apiConfig['base_url']) || empty($this->apiConfig['api_key_name']) || empty($this->apiConfig['api_key'])) {
            $errorMessage = "Configurazione provider API '{$this->activeProvider}' non trovata o incompleta in config/services.php. Controlla la sezione '{$configPath}' e le relative variabili d'ambiente.";
            Log::error($errorMessage, ['loaded_config' => $this->apiConfig]);
            throw new \Exception($errorMessage);
        }
        
        $this->baseUrl = rtrim($this->apiConfig['base_url'], '/');
        $this->apiKey = $this->apiConfig['api_key'];
        $this->apiKeyName = $this->apiConfig['api_key_name'];
        
        if (empty($this->apiKey)) {
            $errorMessage = "Chiave API ({$this->apiKeyName}) per il provider '{$this->activeProvider}' non configurata nel file .env (variabile: {$this->getApiKeyEnvVariable($this->activeProvider)}).";
            Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }
        
        Log::info("PlayerStatsApiService initializzato con provider: {$this->activeProvider}, Base URL: {$this->baseUrl}");
    }
    
    private function getApiKeyEnvVariable(string $provider): string
    {
        if ($provider === 'football_data_org') {
            return 'FOOTBALL_DATA_API_KEY';
        }
        return 'CHIAVE_API_SCONOSCIUTA';
    }
    
    // In PlayerStatsApiService.php, verifica il costruttore o makeRequest
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $url = "{$this->baseUrl}/{$endpoint}";
        
        $teamLogger = Log::build(['driver' => 'single', 'path' => storage_path('logs/Teams/TeamHistoricalStanding.log')]);
        
        $cacheKey = "player_stats_api_" . md5($url . json_encode($params));
        
        if (Cache::has($cacheKey)) {
            Log::info("PlayerStatsApiService Cache Hit per {$endpoint}");
            return Cache::get($cacheKey);
        }
        
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => trim($this->apiKey),
            ])->retry(3, 1000)->get($url, $params);
            
            if ($response->failed()) {
                $teamLogger->error("ERRORE API:", [
                    'status' => $response->status(),
                    'conf_key_usata' => substr($this->apiKey, 0, 5) . "...",
                    'body' => $response->json()
                ]);
                return null;
            }
            
            $data = $response->json();
            Cache::put($cacheKey, $data, 86400); // Cache per 24 ore
            
            return $data;
        } catch (\Exception $e) {
            $teamLogger->error("ECCEZIONE: " . $e->getMessage());
            return null;
        }
    }
    
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
    
    public function getTeamsForCompetitionAndSeason(string $competitionCode, int $seasonStartYear): ?array
    {
        $endpoint = "competitions/{$competitionCode}/teams";
        $params = ['season' => $seasonStartYear];
        return $this->makeRequest($endpoint, $params);
    }
    
    public function getStandingsForCompetitionAndSeason(string $competitionCode, int $seasonStartYear): ?array
    {
        $endpoint = "competitions/{$competitionCode}/standings";
        
        // Passiamo l'anno come parametro, come hai fatto su Postman
        $params = ['season' => $seasonStartYear];
        
        Log::info("Chiamata API: {$endpoint}", $params);
        
        // Assicurati che makeRequest accetti il secondo parametro $params
        return $this->makeRequest($endpoint, $params);
    }
    
    public function getPlayerDetails(int $apiPlayerId): ?array
    {
        $endpoint = "persons/{$apiPlayerId}";
        return $this->makeRequest($endpoint);
    }
    
    public function getTeamSquad(int $apiTeamId): ?array
    {
        $endpoint = "teams/{$apiTeamId}";
        return $this->makeRequest($endpoint);
    }
    
    // ===================================================================
    //  <<<<<<<<<<<<<<<<<< FUNZIONE MANCANTE AGGIUNTA QUI >>>>>>>>>>>>>>>>>
    // ===================================================================
    /**
     * Recupera la rosa (squad) per una squadra specifica in una data stagione.
     */
    public function getPlayersForTeamAndSeason(int $teamApiId, int $seasonYear): ?array
    {
        $endpoint = "teams/{$teamApiId}";
        $params = ['season' => $seasonYear];
        
        $response = $this->makeRequest($endpoint, $params);
        
        // La rosa dei giocatori è nell'array 'squad' della risposta API di football-data.org
        // Lo restituiamo in un formato che il nostro comando si aspetta.
        if (isset($response['squad'])) {
            return ['players' => $response['squad']];
        }
        
        Log::warning("Nessun array 'squad' trovato nella risposta API per il team ID: {$teamApiId}");
        return null;
    }
}