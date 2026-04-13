<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamSeason;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\League;
use Tests\TestCase;

class PlayersHistoricalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Base: Lega Serie A
        League::create([
            'id' => 1,
            'name' => 'Serie A',
            'api_id' => 2019,
            'country_code' => 'IT'
        ]);

        // 2. Setup Stagione 2025
        Season::create([
            'id' => 1,
            'season_year' => 2025,
            'is_current' => true,
            'start_date' => '2025-08-20',
            'end_date' => '2026-05-20',
        ]);

        // 3. Setup Team: Milan
        $milan = Team::create([
            'id' => 1,
            'name' => 'AC Milan',
            'short_name' => 'Milan',
            'api_id' => 108,
        ]);

        TeamSeason::create([
            'team_id' => $milan->id,
            'season_id' => 1,
            'league_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_it_performs_comprehensive_matching_l1_to_l4()
    {
        // --- PREPARAZIONE DATI LOCALI ---
        
        // P-L1: Giocatore esistente con ID API (Match L1 certo)
        $milanId = 1;
        Player::create([
            'name' => 'Rafael Leao',
            'api_football_data_id' => 12345,
            'role' => 'A'
        ]);

        // P-L2: Giocatore esistente senza ID API nella stessa squadra (Match L2)
        Player::create([
            'name' => 'Theo Hernandez',
            'role' => 'D'
        ]);
        PlayerSeasonRoster::create([
            'player_id' => 2, // Theo
            'team_id' => $milanId,
            'season_id' => 1,
            'role' => 'D'
        ]);

        // P-L3: Giocatore esistente in un'altra squadra (Trasferito - Match L3 con filtro ruolo)
        Player::create([
            'name' => 'Alvaro Morata',
            'role' => 'A'
        ]);
        // Morata NON è nel roster del Milan a DB, ma apparirà nella squadra Milan dell'API
        
        // P-L3-WrongRole: Un "Alvaro Morata" difensore (non deve matchare per via del filtro ruolo)
        Player::create([
            'name' => 'Alvaro Morata Pseudo',
            'role' => 'D'
        ]);

        // --- MOCK API ---
        
        $apiSquadData = [
            'squad' => [
                [
                    'id' => 12345, // Rafael Leao (L1)
                    'name' => 'Rafael Leão',
                    'position' => 'Forward',
                    'dateOfBirth' => '1999-06-10'
                ],
                [
                    'id' => 67890, // Theo Hernandez (L2)
                    'name' => 'Theo Hernández',
                    'position' => 'Defender',
                    'dateOfBirth' => '1997-10-06'
                ],
                [
                    'id' => 11111, // Alvaro Morata (L3 Global)
                    'name' => 'Álvaro Morata',
                    'position' => 'Forward',
                    'dateOfBirth' => '1992-10-23'
                ],
                [
                    'id' => 99999, // Nuovo Giocatore (L4)
                    'name' => 'Francesco Camarda',
                    'position' => 'Forward',
                    'dateOfBirth' => '2008-03-10'
                ]
            ]
        ];

        Http::fake([
            'api.football-data.org/v4/teams/108*' => Http::response($apiSquadData, 200),
        ]);

        // --- ESECUZIONE ---
        
        Cache::forget('sync_rose_progress');
        
        $this->artisan('players:historical-sync', [
            '--season' => 2025,
            '--threshold' => 85,
        ])->assertExitCode(0);

        // --- ASSERTIONS ---

        // 1. Verifica L1: Rafael Leao (ID API invariato, anagrafica arricchita)
        $this->assertDatabaseHas('players', [
            'api_football_data_id' => 12345,
            'name' => 'Rafael Leao'
        ]);

        // 2. Verifica L2: Theo Hernandez (Agganciato ID API tramite nome locale nella stessa squadra)
        $this->assertDatabaseHas('players', [
            'api_football_data_id' => 67890,
            'name' => 'Theo Hernandez'
        ]);

        // 3. Verifica L3: Alvaro Morata (Agganciato ID API tramite ricerca globale e filtro RUOLO A)
        $this->assertDatabaseHas('players', [
            'api_football_data_id' => 11111,
            'name' => 'Alvaro Morata'
        ]);
        
        // Morata Pseudo (Difensore) non deve aver ricevuto l'ID 11111
        $this->assertDatabaseMissing('players', [
            'name' => 'Alvaro Morata Pseudo',
            'api_football_data_id' => 11111
        ]);

        // 4. Verifica L4: Francesco Camarda (Nuovo calciatore creato)
        $this->assertDatabaseHas('players', [
            'api_football_data_id' => 99999,
            'name' => 'Francesco Camarda'
        ]);

        // 5. Verifica Roster: Tutti e 4 devono essere nel roster Milan 2025
        $this->assertEquals(4, PlayerSeasonRoster::where('team_id', 1)->where('season_id', 1)->count());

        // 6. Verifica Progresso Cache
        $progress = Cache::get('sync_rose_progress');
        $this->assertNotNull($progress);
        $this->assertEquals(100, $progress['percent']);
        $this->assertEquals('Milan', $progress['team']);
    }

    public function test_it_skips_on_403_forbidden_rate_limit()
    {
        Http::fake([
            'api.football-data.org/v4/teams/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $this->artisan('players:historical-sync', [
            '--season' => 2025,
        ])->assertExitCode(0);

        // Nessun giocatore creato/aggiornato
        $this->assertDatabaseCount('player_season_roster', 0);
    }
}
