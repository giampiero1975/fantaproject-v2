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
        Schema::create('teams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('short_name')->nullable();
            $table->string('tla')->nullable();
            $table->string('crest_url')->nullable();
            $table->string('fbref_url')->nullable();
            $table->boolean('serie_a_team')->default(false)->index();
            $table->integer('tier')->nullable()->index();
            $table->integer('api_football_data_id')->nullable()->unique()->comment('ID usato da football-data.org');
            $table->string('league_code')->nullable()->index()->comment('Codice lega attuale (SA, SB) da football-data.org');
            $table->integer('season_year')->nullable()->comment('Anno della stagione di riferimento per i dati della squadra (es. da API)');
            $table->timestamps();
            $table->unsignedBigInteger('fanta_platform_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
