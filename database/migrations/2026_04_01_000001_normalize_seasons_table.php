<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aggiunta colonne api_id e fbref_id
        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedBigInteger('api_id')->nullable()->unique()->after('id');
            $table->string('fbref_id')->nullable()->after('api_id');
        });

        // 2. Travaso degli ID attuali verso api_id
        DB::table('seasons')->update(['api_id' => DB::raw('id')]);

        // 3. Recupero mappatura per aggiornare le pivot
        // Ordiniamo per anno per avere ID interni (1, 2, 3...) logici cronologicamente
        $seasons = DB::table('seasons')->orderBy('season_year', 'asc')->get();
        $mapping = [];
        $nextId = 1;

        foreach ($seasons as $season) {
            $mapping[$season->id] = $nextId++;
        }

        // 4. Disabilitazione temporanea dei vincoli per lo swap degli ID (solo MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Aggiornamento tabella pivot team_season
        foreach ($mapping as $oldId => $newId) {
            DB::table('team_season')->where('season_id', $oldId)->update(['season_id' => $newId]);
        }

        // Aggiornamento tabella seasons (cambio ID primario)
        foreach ($mapping as $oldId => $newId) {
            DB::table('seasons')->where('api_id', $oldId)->update(['id' => $newId]);
        }

        // 5. Trasformazione della colonna ID in AUTO_INCREMENT (solo MySQL)
        // Nota: In SQLite questo non è necessario o viene gestito diversamente nelle tabelle in-memory.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE seasons MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Rimuoviamo l'auto-increment prima di droppare le colonne
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE seasons MODIFY COLUMN id BIGINT UNSIGNED;');
        }

        // Ripristino degli ID originali da api_id (se possibile)
        $seasons = DB::table('seasons')->whereNotNull('api_id')->get();
        foreach ($seasons as $season) {
            $oldApiId = $season->api_id;
            DB::table('team_season')->where('season_id', $season->id)->update(['season_id' => $oldApiId]);
            DB::table('seasons')->where('id', $season->id)->update(['id' => $oldApiId]);
        }

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['api_id', 'fbref_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
