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
        Schema::table('player_projections_season', function (Blueprint $table) {
            $table->foreign(['player_id'])->references(['id'])->on('players')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_projections_season', function (Blueprint $table) {
            $table->dropForeign('player_projections_season_player_id_foreign');
        });
    }
};
