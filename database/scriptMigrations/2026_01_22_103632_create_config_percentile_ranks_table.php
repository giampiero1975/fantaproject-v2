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
        Schema::create('config_percentile_ranks', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('metric_name');
            $table->string('role', 5);
            $table->integer('percentile_rank');
            $table->decimal('threshold_value', 10, 4);

            $table->index(['metric_name', 'role'], 'idx_metric_role');
            $table->unique(['metric_name', 'role', 'percentile_rank'], 'metric_role_percentile_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_percentile_ranks');
    }
};
