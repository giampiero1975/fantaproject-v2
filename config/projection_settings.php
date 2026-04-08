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

        // ── Pesi Fattore Potenza (Componente Cinetica) ──────────────────
        'weights' => [
            'points'        => 0.60,
            'goals_for'     => 0.28,
            'goals_against' => 0.12,
        ],

        // ── Normalizzazione Serie B (Coefficienti Malus Aggressivi) ──
        'serie_b_coefficients' => [
            'points'        => 0.55,
            'goals_for'     => 0.45,
            'goals_against' => 0.75,
        ],

        // ── Soglie Calibrate (Target Matchdaay 31) ──────────────────────
        'thresholds' => [
            't1' => 7.5,
            't2' => 9.5,
            't3' => 12.5,
            't4' => 13.5,
            // Oltre 13.5 -> Tier 5
        ],
    ],

];
