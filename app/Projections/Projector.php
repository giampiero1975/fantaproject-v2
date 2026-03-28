<?php

namespace App\Projections;

use Spatie\EventSourcing\EventHandlers\Projectors\Projector as SpatieProjector;

abstract class Projector extends SpatieProjector
{
    /**
     * Resetta lo stato della proiezione.
     * Utile in fase di rebuild.
     */
    abstract public function reset(): void;
}