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
        Schema::create('user_league_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index('user_league_profiles_user_id_foreign');
            $table->string('league_name')->default('La Mia Lega Fantacalcio');
            $table->integer('total_budget')->default(500)->comment('Budget totale per l\'asta');
            $table->integer('num_goalkeepers')->default(3)->comment('Numero portieri in rosa');
            $table->integer('num_defenders')->default(8)->comment('Numero difensori in rosa');
            $table->integer('num_midfielders')->default(8)->comment('Numero centrocampisti in rosa');
            $table->integer('num_attackers')->default(6)->comment('Numero attaccanti in rosa');
            $table->integer('num_participants')->default(10)->comment('Numero partecipanti alla lega');
            $table->text('scoring_rules')->nullable()->comment('Regole di punteggio specifiche (es. JSON o testo semplice)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_league_profiles');
    }
};
