<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('historical_player_stats', function (Blueprint $table) {
            $table->index('season_id', 'historical_stats_season_id_idx');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->index('deleted_at', 'players_deleted_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_player_stats', function (Blueprint $table) {
            $table->dropIndex('historical_stats_season_id_idx');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('players_deleted_at_idx');
        });
    }
};
