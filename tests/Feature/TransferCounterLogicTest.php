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
use Illuminate\Support\Collection;
use Tests\TestCase;

class TransferCounterLogicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica che il contatore dei trasferimenti funzioni correttamente
     * basandosi sugli anni (season_year) e non sugli ID stagionali.
     *
     * TuttiSheetImport ora usa collection() (ToCollection), non model().
     * Il trasferimento viene rilevato confrontando il roster della stagione
     * precedente (in-memory) con la squadra indicata nel file corrente.
     */
    public function test_transfer_counter_ignores_season_id_order(): void
    {
        // 1. SETUP
        $league = League::create([
            'id'           => 1,
            'name'         => 'Serie A',
            'api_id'       => 2019,
            'country_code' => 'IT',
        ]);

        // Stagione 2021 con ID ALTO (simula il caso reale con ID non ordinati)
        $s2021 = Season::create([
            'id'          => 10,
            'season_year' => 2021,
            'is_current'  => false,
            'start_date'  => '2021-08-01',
            'end_date'    => '2022-05-31',
        ]);

        // Stagione 2022 con ID BASSO
        $s2022 = Season::create([
            'id'          => 5,
            'season_year' => 2022,
            'is_current'  => false,
            'start_date'  => '2022-08-01',
            'end_date'    => '2023-05-31',
        ]);

        // Squadre
        $juve = Team::create(['name' => 'Juventus', 'api_id' => 109]);
        $roma = Team::create(['name' => 'Roma',     'api_id' => 100]);

        TeamSeason::create(['team_id' => $juve->id, 'season_id' => $s2021->id, 'league_id' => $league->id, 'is_active' => true]);
        TeamSeason::create(['team_id' => $roma->id, 'season_id' => $s2022->id, 'league_id' => $league->id, 'is_active' => true]);

        // Dybala nella Juventus nel 2021
        $dybala = Player::create(['name' => 'Paulo Dybala', 'fanta_platform_id' => 455]);
        PlayerSeasonRoster::create([
            'player_id' => $dybala->id,
            'season_id' => $s2021->id,
            'team_id'   => $juve->id,
            'role'      => 'A',
        ]);

        // 2. ESECUZIONE IMPORT 2022 via collection() (Dybala -> Roma)
        $importer = new TuttiSheetImport($s2022->id);

        // collection() riceve una Collection di oggetti-riga con chiavi lowercase
        $rows = new Collection([
            new \Illuminate\Support\Fluent([
                'id'      => '455',
                'nome'    => 'Paulo Dybala',
                'squadra' => 'Roma',
                'r'       => 'A',
                'rm'      => null,
                'qti'     => '25',
                'qta'     => '29',
                'fvm'     => '0',
            ]),
        ]);

        $importer->collection($rows);

        // 3. ASSERZIONI
        // La logica cerca il roster della stagione 2021 (season_year = 2022-1)
        // e trova Dybala nella Juventus → transferCount = 1.
        $this->assertEquals(1, $importer->getTransferCount(), 'ERRORE: Trasferimento non rilevato nonostante il cambio squadra.');
        $this->assertEquals(0, $importer->getConfirmedCount(), 'ERRORE: Marcato come confermato invece di trasferimento.');

        echo "\n✅ LOGICA CONTATORE VALIDATA (2021 ID:10 -> 2022 ID:5)\n";
        echo '   - Trasferimenti rilevati: ' . $importer->getTransferCount() . "\n";
    }
}
