<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return; // SQLite ha limiti noti col drop column + indici
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['serie_a_team', 'league_code', 'season']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('serie_a_team')->default(0);
            $table->string('league_code')->nullable();
            $table->integer('season')->nullable();
        });
    }
};
