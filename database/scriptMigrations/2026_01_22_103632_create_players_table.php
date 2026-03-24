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
        Schema::create('players', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id')->nullable()->index('players_team_id_foreign');
            $table->string('fbref_url')->nullable();
            $table->unsignedBigInteger('parent_team_id')->nullable()->index('players_parent_team_id_foreign');
            $table->integer('fanta_platform_id')->nullable()->unique()->comment('ID dal file Quotazioni (colonna "Id")');
            $table->integer('api_football_data_id')->nullable()->unique()->comment('ID del giocatore sull\'API Football-Data.org');
            $table->string('name')->comment('Nome del giocatore (colonna "Nome")');
            $table->string('team_name')->comment('Squadra attuale del giocatore (colonna "Squadra")');
            $table->char('role')->nullable()->comment('Ruolo ufficiale (P,D,C,A - colonna "R")');
            $table->integer('initial_quotation')->nullable()->comment('Quotazione Iniziale (colonna "Qt. I")');
            $table->integer('current_quotation')->nullable()->comment('Quotazione Attuale (colonna "Qt. A")');
            $table->integer('fvm')->nullable()->comment('Fantavalore Valore di Mercato (colonna "FVM")');
            $table->date('date_of_birth')->nullable()->comment('Data di nascita del giocatore');
            $table->json('detailed_position')->nullable()->comment('Posizione dettagliata da API (es. Central Midfield)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
