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
            $table->foreign(['parent_team_id'])->references(['id'])->on('teams')->onUpdate('restrict')->onDelete('set null');
            $table->foreign(['team_id'])->references(['id'])->on('teams')->onUpdate('restrict')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign('players_parent_team_id_foreign');
            $table->dropForeign('players_team_id_foreign');
        });
    }
};
