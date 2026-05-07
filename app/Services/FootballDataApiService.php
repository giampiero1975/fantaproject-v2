<?php

namespace App\Services;

use App\Models\Team;
use App\Models\League;
use App\Models\Season;
use App\Models\TeamSeason;
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
        // Fallback provvisorio per l'allineamento iniziale (verrà corretto dinamicamente dall'ID fornito dall'API)
        $mapping = config('leagues_mapping', []);
        $fbrefId = ($leagueCode === 'SA') ? ($mapping[2019] ?? null) : null;

        $leagueModel = League::updateOrCreate(
            ['name' => ($leagueCode === 'SA') ? 'Serie A' : $leagueCode],
            [
                'country_code' => ($leagueCode === 'SA') ? 'ITA' : $leagueCode,
                'fbref_id' => $fbrefId
            ]
        );

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

            $apiData = $response->json();
            
            // 1. IDEMPOTENZA DINAMICA (Leagues): Leggiamo l'id fornito direttamente dall'API per evitare hardcoding
            $competitionData = $apiData['competition'] ?? null;
            if ($competitionData && isset($competitionData['id'])) {
                $leagueId = (int) $competitionData['id'];
                $leagueName = $competitionData['name'] ?? 'Serie A';
                $dynamicFbrefId = $mapping[$leagueId] ?? null;

                // Allineiamo il modello League usando l'api_id ritornato dal provider ufficiale
                $leagueModel = League::updateOrCreate(
                    ['api_id' => $leagueId],
                    [
                        'name' => $leagueName,
                        'country_code' => $competitionData['code'] ?? 'SA',
                        'fbref_id' => $dynamicFbrefId
                    ]
                );

                // Eliminiamo eventuali record orfani duplicati senza api_id creati precedentemente con lo stesso nome
                League::where('name', $leagueName)->whereNull('api_id')->delete();
            }

            $teams = $apiData['teams'] ?? [];

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
                $seasonModel = Season::firstOrCreate(['season_year' => $season]);

                TeamSeason::updateOrCreate(
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
