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
        // 1. Pulisci orfani per evitare errori di vincolo di integrità referenziale
        DB::statement("DELETE FROM team_historical_standings WHERE team_id NOT IN (SELECT id FROM teams)");
        DB::statement("DELETE FROM player_season_roster WHERE team_id IS NOT NULL AND team_id NOT IN (SELECT id FROM teams)");

        // 2. Aggiorna team_historical_standings
        Schema::table('team_historical_standings', function (Blueprint $table) {
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');
        });

        // 3. Aggiorna player_season_roster (cambio da set null a cascade)
        Schema::table('player_season_roster', function (Blueprint $table) {
            $table->dropForeign('player_season_roster_team_id_foreign');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_season_roster', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('set null');
        });

        Schema::table('team_historical_standings', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
    }
};
