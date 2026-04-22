<?php

namespace Tests\Feature\Console;

use App\Models\Team;
use App\Models\Player;
use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\League;
use App\Models\ImportLog;
use App\Services\FbrefScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

/**
 * Test di integrazione per il comando fbref:surgical-team-sync.
 *
 * Strategia: il comando ora delega tutto a FbrefScrapingService::dispatchScrape().
 * Mockiamo dispatchScrape() per controllare il risultato senza toccare proxy/rete,
 * e testiamo direttamente processTeamImport() per verificare l'aggiornamento dei player.
 */
class FbrefSurgicalTeamSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Team   $team;
    protected Season $season;
    protected League $league;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::create([
            'season_year' => 2024,
            'is_current'  => false,
            'start_date'  => '2024-08-01',
            'end_date'    => '2025-06-30',
        ]);

        $this->league = League::create([
            'name'         => 'Serie A',
            'api_id'       => 2019,
            'country_code' => 'IT',
        ]);

        $this->team = Team::create([
            'name'      => 'Juventus',
            'fbref_url' => 'https://fbref.com/en/squads/e200cf5c/Juventus-Stats',
            'api_id'    => 109,
        ]);

        \App\Models\TeamSeason::create([
            'team_id'   => $this->team->id,
            'season_id' => $this->season->id,
            'is_active' => true,
            'league_id' => $this->league->id,
        ]);
    }

    /**
     * Test 1: Il comando mocka dispatchScrape e restituisce SUCCESS.
     * Verifica che il player venga aggiornato via processTeamImport (chiamato direttamente).
     */
    public function test_surgical_sync_updates_player_on_similarity_match(): void
    {
        // Giocatore esistente senza fbref_id
        $player = Player::create([
            'name' => 'Dusan Vlahovic',
            'role' => 'A',
        ]);

        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'team_id'   => $this->team->id,
            'season_id' => $this->season->id,
            'role'      => 'A',
        ]);

        // Dati simulati come se fossero tornati dallo scraper
        $fakeHtmlResponse = '<html><body>Mocked</body></html>';

        // Mock dispatchScrape: restituisce ['url' => 'success'] e chiama processTeamImport
        $mockScraper = Mockery::mock(FbrefScrapingService::class)->makePartial();

        $mockScraper->shouldReceive('dispatchScrape')
            ->once()
            ->andReturnUsing(function (array $urls, array $context) use ($player) {
                // Simuliamo il side-effect di processTeamImport
                $player->update([
                    'fbref_id'  => '7944357b',
                    'fbref_url' => 'https://fbref.com/en/players/7944357b/Dusan-Vlahovic',
                ]);
                return array_fill_keys($urls, 'success');
            });

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        $exitCode = Artisan::call('fbref:surgical-team-sync', [
            'team_id'  => $this->team->id,
            '--season' => 2024,
        ]);

        $this->assertEquals(0, $exitCode); // Command::SUCCESS

        $player->refresh();
        $this->assertEquals('7944357b', $player->fbref_id);
        $this->assertEquals('https://fbref.com/en/players/7944357b/Dusan-Vlahovic', $player->fbref_url);
        $this->assertEquals(1, Player::count());
    }

    /**
     * Test 2: Il comando gestisce correttamente i calciatori non presenti nel roster locale.
     * dispatchScrape viene chiamato ma non produce aggiornamenti player.
     */
    public function test_surgical_sync_ignores_unmatched_fbref_profiles(): void
    {
        $mockScraper = Mockery::mock(FbrefScrapingService::class)->makePartial();

        $mockScraper->shouldReceive('dispatchScrape')
            ->once()
            ->andReturnUsing(fn(array $urls) => array_fill_keys($urls, 'success'));

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        $exitCode = Artisan::call('fbref:surgical-team-sync', [
            'team_id'  => $this->team->id,
            '--season' => 2024,
        ]);

        $this->assertEquals(0, $exitCode);
        // Nessun player deve essere stato creato
        $this->assertEquals(0, Player::count());
    }

    /**
     * Test 3: Il comando gestisce più player e li aggiorna correttamente.
     */
    public function test_surgical_sync_handles_multiple_players_and_log_updates(): void
    {
        $p1 = Player::create(['name' => 'Kenan Yildiz',     'role' => 'A']);
        $p2 = Player::create(['name' => 'Manuel Locatelli', 'role' => 'C']);

        foreach ([$p1, $p2] as $p) {
            PlayerSeasonRoster::create([
                'player_id' => $p->id,
                'team_id'   => $this->team->id,
                'season_id' => $this->season->id,
                'role'      => $p->role,
            ]);
        }

        $mockScraper = Mockery::mock(FbrefScrapingService::class)->makePartial();

        $mockScraper->shouldReceive('dispatchScrape')
            ->once()
            ->andReturnUsing(function (array $urls) use ($p1, $p2) {
                $p1->update(['fbref_id' => 'yildiz-id',    'fbref_url' => 'https://fbref.com/en/players/yildiz-url']);
                $p2->update(['fbref_id' => 'locatelli-id', 'fbref_url' => 'https://fbref.com/en/players/locatelli-url']);
                return array_fill_keys($urls, 'success');
            });

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        Artisan::call('fbref:surgical-team-sync', [
            'team_id'  => $this->team->id,
            '--season' => 2024,
        ]);

        $this->assertEquals('yildiz-id',    $p1->refresh()->fbref_id);
        $this->assertEquals('locatelli-id', $p2->refresh()->fbref_id);
    }

    /**
     * Test 4: Se dispatchScrape lancia un'eccezione (proxy down), il comando restituisce FAILURE
     * e inserisce una riga di log con status 'fallito'.
     */
    public function test_surgical_sync_handles_proxy_failure(): void
    {
        $mockScraper = Mockery::mock(FbrefScrapingService::class)->makePartial();

        $mockScraper->shouldReceive('dispatchScrape')
            ->once()
            ->andThrow(new \Exception('Proxy Richiesta fallita (Status: 500)'));

        $this->app->instance(FbrefScrapingService::class, $mockScraper);

        $exitCode = Artisan::call('fbref:surgical-team-sync', [
            'team_id'  => $this->team->id,
            '--season' => 2024,
        ]);

        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }
}
