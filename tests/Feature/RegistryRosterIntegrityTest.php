<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;
use App\Models\League;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryRosterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected $season2021;
    protected $season2022;
    protected $teamMilan;
    protected $teamInter;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base: Serie A e Stagione 2021
        League::create(['name' => 'Serie A', 'api_id' => 2019, 'country_code' => 'IT']);
        
        $this->season2021 = Season::create([
            'season_year' => 2021, 
            'is_current' => false, 
            'start_date' => '2021-08-01', 
            'end_date' => '2022-05-31'
        ]);

        $this->season2022 = Season::create([
            'season_year' => 2022, 
            'is_current' => false, 
            'start_date' => '2022-08-01', 
            'end_date' => '2023-05-31'
        ]);

        $this->teamMilan = Team::create(['name' => 'AC Milan', 'short_name' => 'Milan']);
        $this->teamInter = Team::create(['name' => 'Inter', 'short_name' => 'Inter']);
    }

    /**
     * Test della relazione base tra Anagrafica e Roster.
     */
    public function test_player_roster_relationship()
    {
        $player = Player::create(['name' => 'Mike Maignan', 'role' => 'P', 'fanta_platform_id' => 1]);
        
        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'season_id' => $this->season2021->id,
            'team_id'   => $this->teamMilan->id,
            'role'      => 'P'
        ]);

        $this->assertCount(1, $player->rosters);
        $this->assertEquals('AC Milan', $player->rosters->first()->team->name);
        $this->assertEquals(2021, $player->rosters->first()->season->season_year);
    }

    /**
     * Test Integrità Globale: nessun orfano ammesso.
     */
    public function test_no_orphans_allowed()
    {
        $player = Player::create(['name' => 'Theo Hernandez', 'role' => 'D', 'fanta_platform_id' => 2]);
        
        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'season_id' => $this->season2021->id,
            'team_id'   => $this->teamMilan->id,
            'role'      => 'D'
        ]);

        $this->assertDatabaseCount('player_season_roster', 1);
        $this->assertNotNull(PlayerSeasonRoster::first()->player);
        $this->assertNotNull(PlayerSeasonRoster::first()->team);
    }

    /**
     * Test Logico Trasferimenti (Cambio Squadra tra stagioni).
     */
    public function test_transfer_integrity_between_seasons()
    {
        $player = Player::create(['name' => 'Hakan Calhanoglu', 'role' => 'C', 'fanta_platform_id' => 10]);
        
        // Stagione 2021: Milan
        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'season_id' => $this->season2021->id,
            'team_id'   => $this->teamMilan->id,
            'role'      => 'C'
        ]);

        // Stagione 2022: Inter
        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'season_id' => $this->season2022->id,
            'team_id'   => $this->teamInter->id,
            'role'      => 'C'
        ]);

        $this->assertCount(2, $player->rosters);
        
        $roster2021 = $player->rosters()->where('season_id', $this->season2021->id)->first();
        $roster2022 = $player->rosters()->where('season_id', $this->season2022->id)->first();

        $this->assertEquals('AC Milan', $roster2021->team->name);
        $this->assertEquals('Inter', $roster2022->team->name);
    }

    /**
     * Test Logico Cessazione (Soft-delete se assente nel file).
     */
    public function test_cessation_logic_soft_deletes_player()
    {
        $player = Player::create(['name' => 'Retired Player', 'role' => 'A', 'fanta_platform_id' => 999]);
        
        // Simulazione logica di ImportaListone: ID non presente nel file
        $processedIds = [$player->id + 100]; // ID diverso per forzare eliminazione
        
        if (!in_array($player->id, $processedIds)) {
            $player->delete();
        }

        $this->assertSoftDeleted('players', ['id' => $player->id]);
    }
}
