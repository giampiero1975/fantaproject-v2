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
        Schema::create('player_season_roster', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('season_id')->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('parent_team_id')->nullable()->index()->comment('Per i prestiti');
            
            $table->char('role', 1)->nullable()->comment('Ruolo (P, D, C, A)');
            $table->json('detailed_position')->nullable()->comment('Posizione dettagliata da API');
            
            $table->integer('initial_quotation')->nullable()->comment('Quotazione Iniziale (qti)');
            $table->integer('current_quotation')->nullable()->comment('Quotazione Attuale (qta)');
            $table->integer('fvm')->nullable()->comment('Fantavalore Valore di Mercato');
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('parent_team_id')->references('id')->on('teams')->onDelete('set null');

            // Unique constraint: un giocatore può avere un solo record di roster per ogni stagione
            $table->unique(['player_id', 'season_id'], 'player_season_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_season_roster');
    }
};
