<?php

namespace Tests\Feature\Console;

use App\Models\Team;
use App\Models\Player;
use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\League;
use App\Services\FbrefScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class FbrefSurgicalTeamSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup Dati Base
        $this->season = Season::create([
            'season_year' => 2024,
            'is_current' => false,
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30'
        ]);

        $this->league = League::create([
            'name' => 'Serie A',
            'api_id' => 2019,
            'country_code' => 'IT'
        ]);

        $this->team = Team::create([
            'name' => 'Juventus',
            'fbref_url' => 'https://fbref.com/en/squads/e200cf5c/Juventus-Stats',
            'api_id' => 109
        ]);

        \App\Models\TeamSeason::create([
            'team_id' => $this->team->id,
            'season_id' => $this->season->id,
            'is_active' => true,
            'league_id' => $this->league->id
        ]);
    }

    public function test_surgical_sync_updates_player_on_similarity_match()
    {
        // Giocatore esistente senza ID FBref
        $player = Player::create([
            'name' => 'Dusan Vlahovic',
            'role' => 'A'
        ]);

        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'team_id' => $this->team->id,
            'season_id' => $this->season->id,
            'role' => 'A'
        ]);

        // Mock del servizio di scraping
        $mockScraper = Mockery::mock(FbrefScrapingService::class);
        $mockScraper->shouldReceive('setTargetUrl')->once();
        $mockScraper->shouldReceive('scrapeTeamStats')->once()->andReturn([
            'stats_standard' => [
                [
                    'Player' => 'Dusan Vlahovic', // Match perfetto
                    'fbref_url_extracted' => 'https://fbref.com/en/players/7944357b/Dusan-Vlahovic',
                    'fbref_id_extracted' => '7944357b'
                ]
            ]
        ]);

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        // Esecuzione Comando
        Artisan::call('fbref:surgical-team-sync', [
            'team_id' => $this->team->id,
            '--season' => 2024
        ]);

        // Verifiche
        $player->refresh();
        $this->assertEquals('7944357b', $player->fbref_id);
        $this->assertEquals('https://fbref.com/en/players/7944357b/Dusan-Vlahovic', $player->fbref_url);
        
        // Verifica che non siano stati creati nuovi calciatori
        $this->assertEquals(1, Player::count());
    }

    public function test_surgical_sync_ignores_unmatched_fbref_profiles()
    {
        // Mock del servizio con un calciatore che NON esiste nel roster locale
        $mockScraper = Mockery::mock(FbrefScrapingService::class);
        $mockScraper->shouldReceive('setTargetUrl');
        $mockScraper->shouldReceive('scrapeTeamStats')->andReturn([
            'stats_standard' => [
                [
                    'Player' => 'Unknown Player',
                    'fbref_url_extracted' => 'https://fbref.com/en/players/xxxx/Unknown',
                    'fbref_id_extracted' => 'xxxx'
                ]
            ]
        ]);

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        Artisan::call('fbref:surgical-team-sync', [
            'team_id' => $this->team->id,
            '--season' => 2024
        ]);

        // Non deve creare il calciatore
        $this->assertEquals(0, Player::count());
    }

    public function test_surgical_sync_handles_multiple_players_and_log_updates()
    {
        $p1 = Player::create(['name' => 'Kenan Yildiz', 'role' => 'A']);
        $p2 = Player::create(['name' => 'Manuel Locatelli', 'role' => 'C']);

        foreach([$p1, $p2] as $p) {
            PlayerSeasonRoster::create([
                'player_id' => $p->id,
                'team_id' => $this->team->id,
                'season_id' => $this->season->id,
                'role' => $p->role
            ]);
        }

        $mockScraper = Mockery::mock(FbrefScrapingService::class);
        $mockScraper->shouldReceive('setTargetUrl');
        $mockScraper->shouldReceive('scrapeTeamStats')->andReturn([
            'stats_standard' => [
                [
                    'Player' => 'Kenan Yildiz',
                    'fbref_url_extracted' => 'https://fbref.com/en/players/yildiz-url',
                    'fbref_id_extracted' => 'yildiz-id'
                ],
                [
                    'Player' => 'Manuel Locatelli',
                    'fbref_url_extracted' => 'https://fbref.com/en/players/locatelli-url',
                    'fbref_id_extracted' => 'locatelli-id'
                ]
            ]
        ]);

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        Artisan::call('fbref:surgical-team-sync', [
            'team_id' => $this->team->id,
            '--season' => 2024
        ]);

        $this->assertEquals('yildiz-id', $p1->refresh()->fbref_id);
        $this->assertEquals('locatelli-id', $p2->refresh()->fbref_id);
    }

    public function test_surgical_sync_handles_proxy_failure()
    {
        // Mock del servizio che ritorna un errore di proxy
        $mockScraper = Mockery::mock(FbrefScrapingService::class);
        $mockScraper->shouldReceive('setTargetUrl');
        $mockScraper->shouldReceive('scrapeTeamStats')->andReturn([
            'error' => 'Errore Proxy API: Proxy Richiesta fallita (Status: 500)'
        ]);

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        // Esecuzione comando (deve restituire FAILURE)
        $exitCode = Artisan::call('fbref:surgical-team-sync', [
            'team_id' => $this->team->id,
            '--season' => 2024
        ]);

        $this->assertEquals(1, $exitCode); // 1 = Command::FAILURE

        // Verifica che il log di importazione rifletta il fallimento
        $log = \App\Models\ImportLog::latest()->first();
        $this->assertEquals('fallito', $log->status);
        $this->assertStringContainsString('ERRORE RISPOSTA SCRAPER', $log->details);
    }
}
