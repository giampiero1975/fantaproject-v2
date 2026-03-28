<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_season', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->unsignedBigInteger('season_id'); // Link con Seasons (football-data API ID)
            $table->foreignId('league_id')->nullable()->constrained('leagues')->onDelete('SET NULL');
            
            $table->integer('tier_stagionale')->nullable();
            $table->integer('posizione_finale')->nullable();
            $table->integer('punti')->nullable();
            $table->boolean('is_active')->default(true); // Se milita nella lega in quella stagione
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_season');
    }
};
