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
        Schema::create('player_projections_season', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('player_id')->nullable();
            $table->string('player_fanta_platform_id')->nullable();
            $table->integer('season_start_year');
            $table->double('avg_rating_proj', 8, 2)->nullable();
            $table->double('fanta_mv_proj', 8, 2)->nullable();
            $table->integer('games_played_proj')->nullable();
            $table->double('total_fanta_points_proj', 8, 2)->nullable();
            $table->double('goals_scored_proj', 8, 2)->nullable();
            $table->double('assists_proj', 8, 2)->nullable();
            $table->double('yellow_cards_proj', 8, 2)->nullable();
            $table->double('red_cards_proj', 8, 2)->nullable();
            $table->double('own_goals_proj', 8, 2)->nullable();
            $table->double('penalties_taken_proj', 8, 2)->nullable();
            $table->double('penalties_scored_proj', 8, 2)->nullable();
            $table->double('penalties_missed_proj', 8, 2)->nullable();
            $table->double('goals_conceded_proj', 8, 2)->nullable();
            $table->double('penalties_saved_proj', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'season_start_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_projections_season');
    }
};
