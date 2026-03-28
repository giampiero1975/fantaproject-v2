<?php

namespace Tests\Feature;

use App\Events\ProjectionCalculationRequested;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerFbrefStat;
use App\Models\ImportLog;
use App\Services\FbrefScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Mockery;
use Livewire\Livewire;

class FbrefScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_scraping_flow_dispatches_event_and_saves_data()
    {
        Event::fake([ProjectionCalculationRequested::class]);

        // 1. Setup Dati
        $team = Team::factory()->create([
            'name' => 'Milan',
            'fbref_url' => 'https://fbref.com/en/squads/dcce17c0/Milan-Stats',
            'serie_a_team' => 1,
            'season_year' => 2025,
            'api_football_data_id' => 123
        ]);

        $player = Player::factory()->create([
            'name' => 'Rafael Leao',
            'team_id' => $team->id,
            'team_name' => 'Milan'
        ]);

        // 2. Mock dello Scraper
        $mockScraper = Mockery::mock(FbrefScrapingService::class);
        $mockScraper->shouldReceive('setTargetUrl')->once();
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
        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        // 3. Esecuzione via Livewire (per assicurarci che sia tutto inizializzato)
        Livewire::test(\App\Filament\Pages\ScrapeFbrefTeams::class)
            ->call('scrapeTeam', $team->id);

        // 4. Veriche
        $this->assertEquals(1, PlayerFbrefStat::count(), 'Nessuna statistica salvata.');
        $stat = PlayerFbrefStat::first();
        $this->assertEquals(10, (int)$stat->goals, 'Goal non corrispondenti.');

        Event::assertDispatched(ProjectionCalculationRequested::class);

        $this->assertEquals(1, ImportLog::where('status', 'success')->count());
    }
}
