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
        Schema::create('player_fbref_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('team_id')->index('player_fbref_stats_team_id_foreign');
            $table->year('season_year');
            $table->string('league_name', 50);
            $table->string('data_source', 50)->default('fbref_html_import');
            $table->string('position')->nullable();
            $table->string('age')->nullable();
            $table->integer('games')->nullable();
            $table->integer('games_starts')->nullable();
            $table->integer('minutes')->nullable();
            $table->decimal('minutes_90s')->nullable();
            $table->decimal('goals')->nullable();
            $table->decimal('assists')->nullable();
            $table->decimal('goals_assists')->nullable();
            $table->integer('cards_yellow')->nullable();
            $table->integer('cards_red')->nullable();
            $table->json('data_team')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['player_id', 'team_id', 'season_year', 'league_name'], 'unique_player_season_league');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_fbref_stats');
    }
};
