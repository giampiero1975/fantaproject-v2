<?php

namespace Tests\Feature;

use App\Events\ProjectionCalculationRequested;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerFbrefStat;
use App\Models\ImportLog;
use App\Models\League;
use App\Models\Season;
use App\Models\TeamSeason;
use App\Services\FbrefScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Mockery;

class FbrefScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_scraping_service_saves_player_stats_and_logs_success()
    {
        Event::fake([ProjectionCalculationRequested::class]);

        // 1. Setup Dati
        $season = Season::create([
            'season_year' => 2025, 
            'is_current' => true,
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30'
        ]);
        
        $league = League::create([
            'name' => 'Serie A',
            'api_id' => 2019,
            'country_code' => 'IT'
        ]);

        $team = Team::factory()->create([
            'name' => 'Milan',
            'fbref_url' => 'https://fbref.com/en/squads/dcce17c0/Milan-Stats',
            'api_id' => 123
        ]);
        
        TeamSeason::create([
            'team_id' => $team->id,
            'season_id' => $season->id,
            'is_active' => true,
            'league_id' => $league->id
        ]);

        $player = Player::factory()->create([
            'name' => 'Rafael Leao',
            'team_id' => $team->id,
            'team_name' => 'Milan'
        ]);

        /**
         * 2. Esecuzione Logica via Service
         * Invece di testare una pagina Filament inesistente, testiamo il Service
         * che viene iniettato nelle azioni delle pagine.
         */
        $service = app(FbrefScrapingService::class);
        
        // Mocking only the network part of the service if it was using external calls, 
        // but here we want to test the data processing flow.
        // We'll mock the internal scraper call to avoid real network hits.
        
        $mockScraper = Mockery::mock(FbrefScrapingService::class)->makePartial();
        $mockScraper->shouldReceive('scrapeTeamStats')->once()->andReturn([
            'stats_standard' => [
                [
                    'Player' => 'Rafael Leao',
                    'stats_standard_goals' => '10',
                    'stats_standard_assists' => '5',
                    'stats_standard_minutes' => '20',
                ]
            ]
        ]);
        
        // Inseriamo manualmente i dati simulando l'azione della pagina
        $result = $mockScraper->scrapeTeam($team->id, $team->fbref_url);

        // 3. Verifiche
        $this->assertEquals(1, PlayerFbrefStat::count(), 'Nessuna statistica salvata.');
        $stat = PlayerFbrefStat::first();
        $this->assertEquals(10, (int)$stat->goals, 'Goal non corrispondenti.');

        Event::assertDispatched(ProjectionCalculationRequested::class);

        $this->assertEquals(1, ImportLog::where('status', 'SUCCESS')->count());
    }
}
