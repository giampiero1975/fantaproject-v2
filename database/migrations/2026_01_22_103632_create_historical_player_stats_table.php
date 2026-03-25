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
        Schema::create('historical_player_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('player_id')->nullable()->index('historical_player_stats_player_id_foreign');
            $table->integer('player_fanta_platform_id')->nullable()->comment('ID dal file Quotazioni o interno per collegare al giocatore');
            $table->string('season_year')->comment('Anno della stagione (es. 2023-24)');
            $table->string('league_name')->nullable()->comment('Lega in cui sono state registrate queste statistiche (e.g., Serie A, Serie B)');
            $table->unsignedBigInteger('team_id')->nullable()->index('historical_player_stats_team_id_foreign');
            $table->string('team_name_for_season')->nullable()->comment('Squadra del giocatore in quella specifica stagione');
            $table->char('role_for_season', 1)->nullable()->comment('Ruolo Classic (P, D, C, A) in quella stagione');
            $table->json('mantra_role_for_season')->nullable()->comment('Ruolo Mantra specifico (es. Por, Dc, M, Pc)');
            $table->integer('games_played')->default(0);
            $table->double('avg_rating', 8, 2)->nullable();
            $table->double('fanta_avg_rating', 8, 2)->nullable();
            $table->decimal('goals_scored')->default(0);
            $table->decimal('goals_conceded')->default(0);
            $table->decimal('penalties_saved')->default(0);
            $table->decimal('penalties_taken')->default(0);
            $table->decimal('penalties_scored')->default(0);
            $table->decimal('penalties_missed')->default(0);
            $table->decimal('assists')->default(0);
            $table->integer('assists_from_set_piece')->nullable()->default(0);
            $table->decimal('yellow_cards')->default(0);
            $table->decimal('red_cards')->default(0);
            $table->decimal('own_goals')->default(0);
            $table->timestamps();
            $table->decimal('xg')->default(0)->comment('Expected Goals (FBref)');
            $table->decimal('xg_assist')->default(0)->comment('Expected Assisted Goals (FBref)');
            $table->integer('gca')->default(0)->comment('Goal Creating Actions');
            $table->integer('passes_progressive_distance')->default(0)->comment('Distanza progressiva passaggi (yards)');
            $table->integer('passes_into_final_third')->default(0)->comment('Passaggi nella trequarti avversaria');
            $table->integer('defense_tackles_won')->default(0)->comment('Tackle vinti');
            $table->integer('defense_blocks')->default(0)->comment('Tiri o passaggi bloccati');
            $table->integer('defense_interceptions')->default(0)->comment('Intercetti');
            $table->integer('defense_recoveries')->default(0)->comment('Palloni recuperati');
            $table->integer('aerials_won')->default(0)->comment('Duelli aerei vinti');
            $table->integer('touches_def_pen_area')->default(0)->comment('Tocchi in area difensiva (per difensori)');

            $table->unique(['player_fanta_platform_id', 'season_year', 'team_id', 'league_name'], 'hist_player_unique_season_team_league');
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
