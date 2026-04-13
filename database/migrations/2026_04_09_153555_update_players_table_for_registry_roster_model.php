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
            // Rimuoviamo le colonne che ora vivono nella pivot player_season_roster
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['team_id']);
                $table->dropForeign(['parent_team_id']);
                
                $table->dropColumn([
                    'team_id',
                    'parent_team_id',
                ]);
            }

            // Queste colonne sono sicure da rimuovere anche su SQLite 
            // perché non hanno vincoli di foreign key espliciti o indici complessi
            $table->dropColumn([
                'team_name',
                'role',
                'initial_quotation',
                'current_quotation',
                'fvm',
                'detailed_position'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('parent_team_id')->nullable()->index();
            $table->string('team_name')->nullable();
            $table->char('role', 1)->nullable();
            $table->integer('initial_quotation')->nullable();
            $table->integer('current_quotation')->nullable();
            $table->integer('fvm')->nullable();
            $table->json('detailed_position')->nullable();

            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('parent_team_id')->references('id')->on('teams');
        });
    }
};
