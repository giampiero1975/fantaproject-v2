<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballDataApiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.football-data.org/v4';

    public function __construct()
    {
        $this->apiKey = env('FOOTBALL_DATA_API_KEY');
    }

    /**
     * Sync teams for a specific league and seasons.
     */
    public function syncTeams(string $leagueCode = 'SA', array $seasons = [2023, 2024, 2025]): void
    {
        foreach ($seasons as $season) {
            Log::info("FootballDataAPI: Recupero squadre per {$leagueCode} stagione {$season}");
            
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey
            ])->get("{$this->baseUrl}/competitions/{$leagueCode}/teams", [
                'season' => $season
            ]);

            if ($response->failed()) {
                Log::error("FootballDataAPI Error ({$season}): " . $response->body());
                continue;
            }

            $teams = $response->json()['teams'] ?? [];

            foreach ($teams as $teamData) {
                // serie_a_team = 1 solo per la stagione corrente (2025)
                $isSerieAActive = ($season === 2025);

                $team = Team::updateOrCreate(
                    ['api_football_data_id' => $teamData['id']],
                    [
                        'name' => $teamData['name'],
                        'short_name' => $teamData['shortName'],
                        'tla' => $teamData['tla'],
                        'crest_url' => $teamData['crest'],
                        'league_code' => $leagueCode,
                        'season_year' => $season,
                    ]
                );

                // Aggiorniamo il flag solo se ? la stagione 2025, 
                // oppure se il team non lo ha già a true (per evitare di sovrascrivere teams attivi)
                if ($isSerieAActive) {
                    $team->update(['serie_a_team' => true]);
                }
            }
            
            // Wait to avoid rate limits (10 req/min for free tier)
            sleep(6);
        }
    }
}
