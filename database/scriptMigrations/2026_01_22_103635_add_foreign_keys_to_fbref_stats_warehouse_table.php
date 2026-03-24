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
        Schema::table('fbref_stats_warehouse', function (Blueprint $table) {
            $table->foreign(['fbref_stats_id'])->references(['id'])->on('player_fbref_stats')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fbref_stats_warehouse', function (Blueprint $table) {
            $table->dropForeign('fbref_stats_warehouse_fbref_stats_id_foreign');
        });
    }
};
