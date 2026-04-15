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
        // Drop existig table if it was created by a previous/broken migration
        Schema::dropIfExists('historical_player_stats');

        Schema::create('historical_player_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_fanta_platform_id')->index()->comment('ID univoco dalla piattaforma Fantacalcio.it (Riferimento Excel)');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');

            // Statistiche Base (Default 0.00)
            $table->integer('games_played')->default(0);
            $table->decimal('average_rating', 8, 2)->default(0.00);
            $table->decimal('fanta_average', 8, 2)->default(0.00);
            $table->decimal('goals', 8, 2)->default(0.00);
            $table->decimal('goals_conceded', 8, 2)->default(0.00);
            $table->decimal('penalties_saved', 8, 2)->default(0.00);
            $table->decimal('penalties_taken', 8, 2)->default(0.00);
            $table->decimal('penalties_scored', 8, 2)->default(0.00);
            $table->decimal('penalties_missed', 8, 2)->default(0.00);
            $table->decimal('assists', 8, 2)->default(0.00);
            $table->integer('assists_from_set_piece')->default(0);
            $table->decimal('yellow_cards', 8, 2)->default(0.00);
            $table->decimal('red_cards', 8, 2)->default(0.00);
            $table->decimal('own_goals', 8, 2)->default(0.00);

            // Statistiche Avanzate (FBref / Extra - Default 0.00)
            $table->decimal('xg', 10, 2)->default(0.00);
            $table->decimal('xg_assist', 10, 2)->default(0.00);
            $table->decimal('gca', 10, 2)->default(0.00);
            $table->decimal('passes_progressive_distance', 10, 2)->default(0.00);
            $table->decimal('passes_into_final_third', 10, 2)->default(0.00);
            $table->decimal('defense_tackles_won', 10, 2)->default(0.00);
            $table->decimal('defense_blocks', 10, 2)->default(0.00);
            $table->decimal('defense_interceptions', 10, 2)->default(0.00);
            $table->decimal('defense_recoveries', 10, 2)->default(0.00);
            $table->decimal('aerials_won', 10, 2)->default(0.00);
            $table->decimal('touches_def_pen_area', 10, 2)->default(0.00);

            $table->timestamps();

            // Vincolo UNIQUE tassativo sulla triade relazionale
            $table->unique(['player_id', 'season_id', 'team_id'], 'unique_stat_per_player_season_team');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_player_stats');
    }
};
