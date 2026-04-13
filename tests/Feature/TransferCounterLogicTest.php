<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Team;
use App\Models\Season;
use App\Models\League;
use App\Models\TeamSeason;
use App\Imports\TuttiSheetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferCounterLogicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica che il contatore dei trasferimenti funzioni correttamente
     * basandosi sugli anni (season_year) e non sugli ID stagionali.
     */
    public function test_transfer_counter_ignores_season_id_order()
    {
        // 1. SETUP
        $league = League::create(['id' => 1, 'name' => 'Serie A', 'api_id' => 2019, 'country_code' => 'IT']);
        
        // Stagione 2021 con ID ALTO (Simula il caso reale)
        $s2021 = Season::create([
            'id' => 10, 
            'season_year' => 2021, 
            'is_current' => false,
            'start_date' => '2021-08-01',
            'end_date' => '2022-05-31'
        ]);
        
        // Stagione 2022 con ID BASSO
        $s2022 = Season::create([
            'id' => 5, 
            'season_year' => 2022, 
            'is_current' => false,
            'start_date' => '2022-08-01',
            'end_date' => '2023-05-31'
        ]);

        // Squadre
        $juve = Team::create(['name' => 'Juventus', 'api_id' => 109]);
        $roma = Team::create(['name' => 'Roma', 'api_id' => 100]);

        TeamSeason::create(['team_id' => $juve->id, 'season_id' => $s2021->id, 'league_id' => $league->id, 'is_active' => true]);
        TeamSeason::create(['team_id' => $roma->id, 'season_id' => $s2022->id, 'league_id' => $league->id, 'is_active' => true]);

        // Player Dybala nel 2021 (Juventus)
        $dybala = Player::create(['name' => 'Paulo Dybala', 'fanta_platform_id' => 455]);
        PlayerSeasonRoster::create([
            'player_id' => $dybala->id,
            'season_id' => $s2021->id,
            'team_id' => $juve->id,
            'role' => 'A'
        ]);

        // 2. ESECUZIONE IMPORT 2022 (Dybala -> Roma)
        // Instanziamo l'importer per la stagione 2022 (ID 5)
        $importer = new TuttiSheetImport($s2022->id);
        
        // Simuliamo la riga del file Excel (Roma)
        $row = [
            'id' => '455',
            'nome' => 'Paulo Dybala',
            'squadra' => 'Roma',
            'r' => 'A',
            'qti' => '25',
            'qta' => '29'
        ];

        $importer->model($row);

        // 3. ASSERZIONI
        // Prima del fix, transferCount sarebbe stato 0 perché cercava season_id < 5.
        // Con il fix, cerca season_year < 2022 e deve trovare il record del 2021 (ID 10).
        $this->assertEquals(1, $importer->getTransferCount(), "ERRORE: Trasferimento non rilevato nonostante il cambio squadra.");
        $this->assertEquals(0, $importer->confirmedCount, "ERRORE: Marcato come confermato invece di trasferimento.");
        
        echo "\n✅ LOGICA CONTATORE VALIDATA (2021 ID:10 -> 2022 ID:5)\n";
        echo "   - Trasferimenti rilevati: " . $importer->getTransferCount() . "\n";
    }
}
