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
        Schema::create('archetype_weights', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('role', 2);
            $table->string('metric_key');
            $table->decimal('pearson_weight', 5, 4)->default(0);
            $table->integer('sample_size')->default(0);
            $table->timestamps();

            $table->unique(['role', 'metric_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archetype_weights');
    }
};
