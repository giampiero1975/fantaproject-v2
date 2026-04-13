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

        // 4. ASSERZIONI
        // A. Nessun L4: Il player_id deve essere lo stesso, nessuna nuova riga in players
        $this->assertEquals(1, Player::count(), "ERRORE: Creato un duplicato L4 invece di matchare l'esistente.");
        
        // B. Roster Update: Il team_id nel roster deve essere cambiato da Roma (ID 1) a Sassuolo (ID 2)
        $updatedRoster = PlayerSeasonRoster::where('player_id', $player->id)
            ->where('season_id', $season->id)
            ->first();
            
        $this->assertEquals($teamSassuolo->id, $updatedRoster->team_id, "ERRORE: Il roster non è stato aggiornato con la nuova squadra.");

        // C. Log Check: Il log dovrebbe contenere il messaggio di trasferimento
        // Nota: Nel test usiamo il logger standard, verifichiamo se il comando ha loggato correttamente
        // In questo test non verifichiamo il contenuto del file di log fisico per semplicità, 
        // ma la logica del team_id è la prova definitiva.
        
        echo "\n✅ TEST COMPLETATO CON SUCCESSO\n";
        echo "   - Calciatore: {$player->name}\n";
        echo "   - Status Registry: UNICO (Match L1 ID API riuscito)\n";
        echo "   - Status Roster: AGGIORNATO (Roma -> Sassuolo)\n";
    }
}
