<?php

namespace App\Projections;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlayerProjector extends Projector
{
    /**
     * Ascolta la creazione di un giocatore (Esempio).
     */
    public function onPlayerCreated($event)
    {
        // Logica di proiezione per player
    }

    public function reset(): void
    {
        Log::info("[PlayerProjector] Resetting players_projection table.");
        try {
            DB::table('players_projection')->truncate();
        } catch (\Throwable $e) {
            Log::error("[PlayerProjector] Error during reset: " . $e->getMessage());
        }
    }
}