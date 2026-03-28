<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Rinomina (richiede doctrine/dbal o Laravel 10+)
            $table->renameColumn('crest_url', 'logo_url');
            $table->renameColumn('api_football_data_id', 'api_id');
            $table->renameColumn('tier', 'tier_globale');
            $table->renameColumn('posizione_media', 'posizione_media_storica');
            
            // Nuove colonne
            $table->string('official_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('logo_url', 'crest_url');
            $table->renameColumn('api_id', 'api_football_data_id');
            $table->renameColumn('tier_globale', 'tier');
            $table->renameColumn('posizione_media_storica', 'posizione_media');
            
            $table->dropColumn('official_name');
        });
    }
};
