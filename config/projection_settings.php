<?php

/**
 * Projection Settings — Parametri Gold Standard Tiers (Ingegneria Cinetica)
 *
 * Configurazione finale consolidata dopo Grid Search Massiva.
 * Modello basato su Fattore Potenza (Punti 60%, GF 28%, GS 12%).
 */
return [

    'tiers' => [
        // ── Struttura Temporale ──────────────────────────────────────────
        'lookback_seasons'     => 4,
        'season_decay_weights' => [10, 4, 2, 1],
        'fixed_divisor'        => 17.0,

        // ── Configurazione Ibrida 70/30 ──────────────────────────────────
        'hybrid' => [
            'enabled' => true,
            'weights' => [
                'historic' => 0.70,
                'momentum' => 0.30,
            ],
            'historic_track' => [
                'lookback' => 4,
                'weights'  => [12, 4, 2, 1],
                'divisor'  => 19.0,
                'use_b_malus' => true,
            ],
            'momentum_track' => [
                'lookback' => 2,
                'weights'  => [10, 4],
                'divisor'  => 14.0,
                'use_b_malus' => false,
            ],
        ],

        // ── Pesi Fattore Potenza (Componente Cinetica) ──────────────────
        'weights' => [
            'points'        => 0.60,
            'goals_for'     => 0.28,
            'goals_against' => 0.12,
        ],

        // ── Normalizzazione Serie B (Moltiplicatori Lineari - Malus 60%) ──
        'serie_b_multipliers' => [
            'points'        => 1.60,
            'goals_for'     => 1.60,
            'goals_against' => 1.00, // Invariato
        ],

        // ── Soglie Calibrate (Target Matchdaay 31) ──────────────────────
        'thresholds' => [
            't1' => 7.5,
            't2' => 9.5,
            't3' => 12.0,
            't4' => 13.0,
            // Oltre 13.5 -> Tier 5
        ],
    ],

];
