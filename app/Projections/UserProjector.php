<?php

namespace App\Projections;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserProjector extends Projector
{
    /**
     * Ascolta la creazione di un utente (Esempio).
     */
    public function onUserCreated($event)
    {
        // Logica di proiezione per user
    }

    public function reset(): void
    {
        Log::info("[UserProjector] Resetting users_projection table.");
        try {
            DB::table('users_projection')->truncate();
        } catch (\Throwable $e) {
            Log::error("[UserProjector] Error during reset: " . $e->getMessage());
        }
    }
}