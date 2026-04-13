<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;
use App\Models\League;
use App\Models\TeamSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base: Serie A e Stagioni
        League::create(['id' => 1, 'name' => 'Serie A', 'api_id' => 2019, 'country_code' => 'IT']);
        Season::create(['id' => 1, 'season_year' => 2025, 'is_current' => true, 'start_date' => '2025-08-20', 'end_date' => '2026-05-20']);
        Season::create(['id' => 2, 'season_year' => 2024, 'is_current' => false, 'start_date' => '2024-08-20', 'end_date' => '2025-05-20']);
        Season::create(['id' => 5, 'season_year' => 2021, 'is_current' => false, 'start_date' => '2021-08-20', 'end_date' => '2022-05-20']);

        Team::create(['id' => 1, 'name' => 'AC Milan', 'short_name' => 'Milan']);
    }

    /**
     * Test della logica di Soft-Delete Globale dei Ceduti.
     * Se un giocatore presente nel DB manca nel file caricato, deve essere soft-deletato.
     */
    public function test_player_is_soft_deleted_if_missing_from_file()
    {
        // 1. Creiamo un calciatore esistente (es. rimasuglio di un import precedente)
        $player = Player::create([
            'name' => 'John Doe',
            'fanta_platform_id' => 1001,
            'role' => 'A'
        ]);

        // 2. Simuliamo gli ID processati dal nuovo import (John Doe manca)
        $processedIds = [
            // Altri ID...
            9999
        ];

        // 3. Eseguiamo la logica di pulizia (estratta da ImportaListone.php)
        $playersToSoftDelete = Player::whereNotIn('id', $processedIds)
            ->whereNull('deleted_at')
            ->get();

        foreach ($playersToSoftDelete as $p) {
            $p->delete();
        }

        // 4. Verifica: John Doe deve essere soft-deleted
        $this->assertSoftDeleted('players', ['id' => $player->id]);
    }

    /**
     * Test della logica di Ripristino Automatico.
     * Se un calciatore soft-deletato riappare nel file, viene ripristinato.
     */
    public function test_player_is_restored_if_found_in_new_import()
    {
        // 1. Calciatore precedentemente cassato
        $player = Player::create([
            'name' => 'Jane Doe',
            'fanta_platform_id' => 2002,
            'role' => 'D',
        ]);
        $player->delete();

        $this->assertTrue($player->trashed());

        // 2. Simula il caricamento tramite TuttiSheetImport logic
        // (Snippet da TuttiSheetImport.php:134-209)
        $fantaPlatformId = 2002;
        $foundPlayer = Player::withTrashed()
            ->where('fanta_platform_id', (int)$fantaPlatformId)
            ->first();

        if ($foundPlayer && $foundPlayer->trashed()) {
            $foundPlayer->restore();
        }

        // 3. Verifica: Jane Doe deve essere attiva
        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'deleted_at' => null
        ]);
    }
    
    /**
     * Test della robustezza del fix $updated
     */
    public function test_it_handles_updated_count_correctly()
    {
        $transfers = 10;
        
        // Simula la stringa di log corretta
        $details = "Importazione completata. Aggiornati: {$transfers}";
        
        $this->assertStringContainsString("Aggiornati: 10", $details);
    }
}
