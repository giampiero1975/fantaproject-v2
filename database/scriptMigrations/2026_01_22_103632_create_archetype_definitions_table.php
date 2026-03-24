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
        Schema::create('archetype_definitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('role', 2);
            $table->string('archetype_name');
            $table->string('metric_key');
            $table->decimal('importance_offset', 3)->default(1);
            $table->timestamps();

            $table->index(['role', 'archetype_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archetype_definitions');
    }
};
