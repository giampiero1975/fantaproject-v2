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
                // is_active = true solo per la stagione corrente (es. 2025)
                $isSerieAActive = ($season === 2025);

                $team = Team::updateOrCreate(
                    ['api_id' => $teamData['id']],
                    [
                        'name' => $teamData['name'],
                        'short_name' => $teamData['shortName'],
                        'tla' => $teamData['tla'],
                        'logo_url' => $teamData['crest'],
                    ]
                );

                // Setup the season & league relations
                $seasonModel = \App\Models\Season::firstOrCreate(['season_year' => $season]);
                $leagueModel = \App\Models\League::firstOrCreate(['country_code' => 'ITA'], ['name' => 'Serie A']);

                \App\Models\TeamSeason::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'season_id' => $seasonModel->id,
                        'league_id' => $leagueModel->id,
                    ],
                    [
                        'is_active' => $isSerieAActive
                    ]
                );
            }
            
            // Wait to avoid rate limits (10 req/min for free tier)
            sleep(6);
        }
    }
}
