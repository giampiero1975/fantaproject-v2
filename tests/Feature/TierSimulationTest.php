<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Team;
use App\Models\Season;
use Tests\TestCase;
use Carbon\Carbon;

class TierSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Blocchiamo il tempo ad Aprile 2026 per coerenza con SeasonHelper
        // SeasonHelper::getCurrentSeason() restituirà 2025.
        // lastConcluded sarà 2024.
        Carbon::setTestNow(Carbon::parse('2026-04-01'));
    }

    /**
     * Test Gold Standard Tier Calculation 
     * Verifica che i Tier vengano assegnati correttamente in base ai dati storici.
     */
    public function test_gold_standard_tier_calculation_logic()
    {
        // 1. Setup Team con scenari diversi
        $inter = Team::factory()->create(['name' => 'Inter', 'api_id' => 100]);
        $sassuolo = Team::factory()->create(['name' => 'Sassuolo', 'api_id' => 101]);
        $cagliari = Team::factory()->create(['name' => 'Cagliari', 'api_id' => 102]);
        $como = Team::factory()->create(['name' => 'Como', 'api_id' => 103]);

        // Stagioni di lookback (2024, 2023, 2022, 2021)
        $seasons = [2024, 2023, 2022, 2021];

        foreach ($seasons as $year) {
            // INTER: Sempre Top (Media ~90 punti in A) -> Tier 1
            DB::table('team_historical_standings')->insert([
                'team_id' => $inter->id,
                'season_year' => $year,
                'league_name' => 'Serie A',
                'points' => 90,
                'played_games' => 38,
                'position' => 1,
                'goals_for' => 90,
                'goals_against' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // SASSUOLO: Sempre Meta Classifica (~45 punti in A) -> Tier 3
            DB::table('team_historical_standings')->insert([
                'team_id' => $sassuolo->id,
                'season_year' => $year,
                'league_name' => 'Serie A',
                'points' => 45,
                'played_games' => 38,
                'position' => 10,
                'goals_for' => 45,
                'goals_against' => 55,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // CAGLIARI: Alternanza A e B -> Tier 4
        // 2024: A (35 pts), 2023: B (high pts), 2022: A (low pts), 2021: B
        $cagliariData = [
            2024 => ['league' => 'Serie A', 'pts' => 35, 'pos' => 16],
            2023 => ['league' => 'Serie B', 'pts' => 70, 'pos' => 4],
            2022 => ['league' => 'Serie A', 'pts' => 30, 'pos' => 18],
            2021 => ['league' => 'Serie B', 'pts' => 65, 'pos' => 5],
        ];
        foreach ($cagliariData as $yr => $d) {
            DB::table('team_historical_standings')->insert([
                'team_id' => $cagliari->id,
                'season_year' => $yr,
                'league_name' => $d['league'],
                'points' => $d['pts'],
                'played_games' => 38,
                'position' => $d['pos'],
                'goals_for' => $d['league'] === 'Serie A' ? 35 : 60,
                'goals_against' => $d['league'] === 'Serie A' ? 50 : 40,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // COMO: Recentemente promosso, solo 1 anno di dati -> Tier 5 (penalizzato dal divisore 17)
        DB::table('team_historical_standings')->insert([
            'team_id' => $como->id,
            'season_year' => 2024,
            'league_name' => 'Serie A',
            'points' => 40,
            'played_games' => 38,
            'position' => 14,
            'goals_for' => 35,
            'goals_against' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Esecuzione Comando
        Artisan::call('teams:update-tiers');

        // 3. Asserzioni
        $inter->refresh();
        $sassuolo->refresh();
        $cagliari->refresh();
        $como->refresh();

        // Inter deve essere Tier 1
        $this->assertEquals(1, $inter->tier_globale, "Inter dovrebbe essere Tier 1");
        
        // Sassuolo deve essere Tier 3 (o 2 in base alle soglie, ma sicuramente non 1 o 5)
        $this->assertLessThanOrEqual(3, $sassuolo->tier_globale);
        $this->assertGreaterThanOrEqual(2, $sassuolo->tier_globale);

        // Cagliari deve essere Tier 4 (Bouncing)
        $this->assertEquals(4, $cagliari->tier_globale, "Cagliari dovrebbe essere Tier 4");

        // Como deve essere Tier 5 (Penalizzato perché manca storia su 17 di divisore fisso)
        $this->assertEquals(5, $como->tier_globale, "Como dovrebbe essere Tier 5 per mancanza di dati storici completi");
        
        // Verifica che la posizione media sia stata salvata
        $this->assertGreaterThan(0, $inter->posizione_media_storica);
    }
}
