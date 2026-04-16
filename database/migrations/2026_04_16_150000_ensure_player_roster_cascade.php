<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pulizia preventiva (Safety check)
        DB::table('player_season_roster')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('players')
                      ->whereColumn('players.id', 'player_season_roster.player_id');
            })
            ->delete();

        // 2. Refresh del vincolo FK
        Schema::table('player_season_roster', function (Blueprint $table) {
            // Rimuoviamo il vincolo se esiste (usando try-catch silezioso o controllo esplicito)
            // Nota: in Laravel Schema non ha un dropForeignIfExists nativo pulito ovunque, 
            // ma dato che abbiamo visto che esiste lo droppiamo esplicitamente.
            $table->dropForeign(['player_id']);
            
            // Lo ricreiamo con ON DELETE CASCADE
            $table->foreign('player_id')
                ->references('id')
                ->on('players')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_season_roster', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
            $table->foreign('player_id')
                ->references('id')
                ->on('players')
                ->onDelete('restrict'); // Torniamo allo stato prudente se necessario
        });
    }
};
