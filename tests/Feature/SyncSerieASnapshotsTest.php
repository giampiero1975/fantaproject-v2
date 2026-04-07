<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamSeason;
use App\Models\ImportLog;
use Tests\TestCase;

class SyncSerieASnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_teams_and_attaches_to_active_season()
    {
        // 1. Arrange: creiamo la lega e la stagione 2025
        \App\Models\League::create([
            'id' => 1,
            'name' => 'Serie A',
            'api_id' => 2019,
            'country_code' => 'IT'
        ]);

        $season = Season::create([
            'id' => 2395,
            'start_date' => '2025-08-24',
            'end_date' => '2026-05-24',
            'season_year' => 2025,
            'is_current' => true
        ]);

        // Mock della risposta API per /teams
        Http::fake([
            'api.football-data.org/v4/competitions/SA/teams*' => Http::response([
                'teams' => [
                    [
                        'id' => 108,
                        'name' => 'AC Milan',
                        'shortName' => 'Milan',
                        'tla' => 'MIL',
                        'crest' => 'https://crests.football-data.org/108.png'
                    ],
                    [
                        'id' => 109,
                        'name' => 'Juventus FC',
                        'shortName' => 'Juventus',
                        'tla' => 'JUV',
                        'crest' => 'https://crests.football-data.org/109.png'
                    ]
                ]
            ], 200)
        ]);

        // 2. Act: Eseguiamo il comando passandogli l'anno 2025
        Artisan::call('football:sync-serie-a', ['season_year' => 2025]);

        // 3. Assert
        $this->assertDatabaseCount('teams', 2);
        
        $this->assertDatabaseHas('teams', [
            'api_id' => 108,
            'short_name' => 'Milan',
            'tla' => 'MIL'
        ]);

        // Verifica la tabella pivot team_season
        $milan = Team::where('api_id', 108)->first();
        $this->assertDatabaseHas('team_season', [
            'team_id' => $milan->id,
            'season_id' => $season->id
        ]);

        // Verifica l'assenza del truncate distruttivo assicurandoci che il record sia stato inserito
        // E verifica del log a db
        $this->assertDatabaseHas('import_logs', [
            'import_type' => 'SYNC_TEAMS',
            'status' => 'SUCCESS'
        ]);
    }

    public function test_it_aborts_if_season_missing()
    {
        // Act: Eseguiamo il comando per un anno senza aver preparato la Season a DB
        Artisan::call('football:sync-serie-a', ['season_year' => 2030]);
        
        // Assert: Nessuna chiamata HTTP dovrebbe partire e niente a DB
        Http::assertNothingSent();
        $this->assertDatabaseCount('teams', 0);
    }
}
