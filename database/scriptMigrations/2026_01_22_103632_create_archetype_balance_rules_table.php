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
        Schema::create('archetype_balance_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('role', 2);
            $table->string('archetype_name');
            $table->decimal('final_weight', 4, 3)->default(0);
            $table->boolean('is_fallback')->default(false);
            $table->json('synergy_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archetype_balance_rules');
    }
};
