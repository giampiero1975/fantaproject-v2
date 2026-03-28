<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectionCalculationRequested
{
    use Dispatchable, SerializesModels;
    
    public int $playerId;
    public int $seasonYear;
    
    public function __construct(int $playerId, int $seasonYear)
    {
        $this->playerId = $playerId;
        $this->seasonYear = $seasonYear;
    }
}