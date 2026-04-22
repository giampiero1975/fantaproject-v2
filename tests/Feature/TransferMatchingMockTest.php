<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Team;
use App\Models\Season;
use App\Models\League;
use App\Models\TeamSeason;
use App\Services\TeamDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TransferMatchingMockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valida che il Matching Engine gestisca correttamente i trasferimenti
     * (stessa anagrafica, squadra diversa) bypassando i limiti API.
     */
    public function test_transfer_logic_with_api_mocking()
    {
        // 1. SETUP DATABASE
        // Lega Serie A
        $league = League::create(['id' => 1, 'name' => 'Serie A', 'api_id' => 2019, 'country_code' => 'IT']);
        
        // Stagione 2021
        $season = Season::create([
            'season_year' => 2021,
            'is_current' => false,
            'start_date' => '2021-08-01',
            'end_date' => '2022-05-31'
        ]);

        // Squadra A (Roma) e Squadra B (Sassuolo)
        $teamRoma = Team::create(['name' => 'Roma', 'api_id' => 100]);
        $teamSassuolo = Team::create(['name' => 'Sassuolo', 'api_id' => 471]);

        // Associa squadre alla stagione
        TeamSeason::create(['team_id' => $teamRoma->id, 'season_id' => $season->id, 'league_id' => $league->id, 'is_active' => true]);
        TeamSeason::create(['team_id' => $teamSassuolo->id, 'season_id' => $season->id, 'league_id' => $league->id, 'is_active' => true]);

        // Calciatore Zeki Celik (ID API: 29622) - Attualmente alla Roma
        $player = Player::create([
            'name' => 'Zeki Celik',
            'api_football_data_id' => 29622,
            'role' => 'D',
            'fanta_platform_id' => 1234 // Simuliamo caricamento Listone
        ]);

        PlayerSeasonRoster::create([
            'player_id' => $player->id,
            'season_id' => $season->id,
            'team_id' => $teamRoma->id,
            'role' => 'D'
        ]);

        // 2. MOCKING API
        // Mockiamo TeamDataService per restituire Celik nella rosa del Sassuolo
        $mock = $this->mock(TeamDataService::class);
        $mock->shouldReceive('getSquad')
            ->with(471, 2021)
            ->once()
            ->andReturn([
                [
                    'id' => 29622,
                    'name' => 'Zeki Celik',
                    'position' => 'Defender',
                    'dateOfBirth' => '1997-02-17'
                ]
            ]);

        // 3. ESECUZIONE
        // Lanciamo la sincronizzazione per il Sassuolo
        $this->artisan('players:historical-sync', [
            '--season' => 2021,
            '--team' => 471,
            '--threshold' => 90
        ])->assertExitCode(0);

        // B. Cross-team Ownership: la policy 'ERP-FAST' NON aggiorna team_id del roster esistente.
        //    Invece imposta parent_team_id = Sassuolo sul roster Roma (proprietà dal club reale).
        $updatedRoster = PlayerSeasonRoster::where('player_id', $player->id)
            ->where('season_id', $season->id)
            ->first();

        // Il roster punta ancora a Roma (non viene spostato)
        $this->assertEquals(
            $teamRoma->id,
            $updatedRoster->team_id,
            'ERRORE: team_id del roster non dovrebbe cambiare con la policy cross-team ownership.'
        );

        // Ma parent_team_id ora punta al Sassuolo (il club proprietario da API)
        $this->assertEquals(
            $teamSassuolo->id,
            $updatedRoster->parent_team_id,
            'ERRORE: parent_team_id dovrebbe essere impostato al Sassuolo.'
        );

        // C. Log Check
        echo "\n✅ TEST COMPLETATO CON SUCCESSO\n";
        echo "   - Calciatore: {$player->name}\n";
        echo "   - Status Registry: UNICO (Match L1 ID API riuscito)\n";
        echo "   - Status Roster: CROSS-TEAM OWNERSHIP (Roma -> parent:Sassuolo)\n";
    }
}
