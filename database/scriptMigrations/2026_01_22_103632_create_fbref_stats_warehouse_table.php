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
        Schema::create('fbref_stats_warehouse', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fbref_stats_id');
            $table->unsignedBigInteger('player_id');
            $table->integer('season_year');
            $table->string('source_type', 20);
            $table->string('metric_key');
            $table->decimal('value', 12, 4);
            $table->decimal('percentile_rank', 5)->nullable()->index();
            $table->timestamps();

            $table->index(['metric_key', 'value']);
            $table->unique(['fbref_stats_id', 'source_type', 'metric_key'], 'unique_metric_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbref_stats_warehouse');
    }
};
