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
        Schema::table('players', function (Blueprint $table) {
            $table->string('fbref_id')->nullable()->after('fbref_url')->index();
            $table->foreignId('parent_team_id')->nullable()->after('api_football_data_id')->constrained('teams')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['parent_team_id']);
            $table->dropColumn(['fbref_id', 'parent_team_id']);
        });
    }
};
