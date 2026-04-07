<?php

/**
 * Projection Settings — Parametri Gold Standard Tiers
 *
 * Ripristino configurazione base a richiesta dell'utente.
 */
return [

    'tier_calculation' => [
        // ── Lookback e pesi temporali ──────────────────────────────────────────
        'lookback_seasons'     => (int) env('TIER_LOOKBACK_SEASONS', env('PREDICTIVE_LOOKBACK_YEARS', 4)),
        'season_decay_weights' => [12, 4, 2, 1],

        // ── Divisore fisso ─────────────────────────────────────────────────────
        'fixed_divisor'        => 19,

        // ── Conversione Serie B ────────────────────────────────────────────────
        'serie_b_conversion_factor' => 1.15,

        // ── Modalità calcolo score ─────────────────────────────────────────────
        'use_points_mode'      => true,

        // ── Malus Decadenza (Trend Penalty) ───────────────────────────────────
        'trend_penalty'        => 1.05,

        // ── Modulatori di contesto (post-ranking) ─────────────────────────────
        'mod_tier_offensive'   => 1.20,
        'mod_tier_defensive'   => 1.20,

        // ── Soglie di assegnazione Tier (Calibrate su Best Accuracy) ──────────
        'tier_thresholds' => [
            1 => 6.4,
            2 => 8.8,
            3 => 12.2,
            4 => 13.2,
        ],
    ],

];
