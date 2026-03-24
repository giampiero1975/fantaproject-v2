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
        Schema::table('player_fbref_stats', function (Blueprint $table) {
            $table->foreign(['player_id'])->references(['id'])->on('players')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['team_id'])->references(['id'])->on('teams')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_fbref_stats', function (Blueprint $table) {
            $table->dropForeign('player_fbref_stats_player_id_foreign');
            $table->dropForeign('player_fbref_stats_team_id_foreign');
        });
    }
};
