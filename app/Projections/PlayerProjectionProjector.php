<?php

namespace App\Projections;

use App\Events\ProjectionCalculationRequested;
use App\Services\ProjectionEngineService;
use Illuminate\Support\Facades\Log;

class PlayerProjectionProjector extends Projector
{
    private $projectionEngine;

    public function __construct(ProjectionEngineService $projectionEngine)
    {
        $this->projectionEngine = $projectionEngine;
    }

    /**
     * Ascolta l'evento di richiesta ricalcolo proiezioni.
     */
    public function onProjectionCalculationRequested(ProjectionCalculationRequested $event)
    {
        Log::info("[PlayerProjectionProjector] Ricevuto evento per squadra ID: {$event->teamId}");
        
        try {
            $this->projectionEngine->calculateForTeam($event->teamId);
            Log::info("[PlayerProjectionProjector] Ricalcolo proiezioni completato per squadra ID: {$event->teamId}");
        } catch (\Throwable $e) {
            Log::error("[PlayerProjectionProjector] Errore nel ricalcolo proiezioni per squadra ID {$event->teamId}: " . $e->getMessage());
        }
    }

    public function reset(): void
    {
        Log::info("[PlayerProjectionProjector] Reset proiezioni richiesto.");
        // Implementazione reset se necessaria
    }
}