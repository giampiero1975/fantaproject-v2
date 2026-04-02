<?php

return [
    /**
     * Numero di anni di storico da analizzare per i modelli predittivi e il monitoraggio.
     * Default: 4 anni passati rispetto alla stagione corrente.
     */
    'lookback_years' => env('PREDICTIVE_LOOKBACK_YEARS', 4),
];
