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
        Schema::table('teams', function (Blueprint $table) {
            // Rimuoviamo il vincolo di unicità precedente (se l'indice ha il nome standard)
            $table->dropUnique('teams_api_football_data_id_unique');
            $table->dropUnique('teams_name_unique');
            
            // Aggiungiamo season se non esiste e rimuoviamo season_year
            if (!Schema::hasColumn('teams', 'season')) {
                $table->integer('season')->nullable()->after('league_code');
            }
            
            if (Schema::hasColumn('teams', 'season_year')) {
                $table->dropColumn('season_year');
            }

            // Nuovi vincoli univoci compositi
            $table->unique(['api_football_data_id', 'season'], 'teams_api_id_season_unique');
            $table->unique(['name', 'season'], 'teams_name_season_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('teams_api_id_season_unique');
            
            if (!Schema::hasColumn('teams', 'season_year')) {
                $table->integer('season_year')->nullable();
            }
            
            if (Schema::hasColumn('teams', 'season')) {
                $table->dropColumn('season');
            }

            $table->unique('api_football_data_id', 'teams_api_football_data_id_unique');
        });
    }
};
