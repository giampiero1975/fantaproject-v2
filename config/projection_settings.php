<?php

/**
 * Projection Settings — Parametri Gold Standard Tiers
 *
 * Calibrati via grid search su 1200 combinazioni (TierSimulationTest)
 * e raffinati con 36 combinazioni di feature evolutive (TierEvolutionTest).
 *
 * Best Evo Config: MAE = 1.1765 | Affinity |Δ|≤3 = 94.1%
 */
return [

    'tier_calculation' => [

        // ── Lookback e pesi temporali ──────────────────────────────────────────
        // Numero di stagioni CONCLUSE da analizzare. Letto da .env (PREDICTIVE_LOOKBACK_YEARS).
        // VINCOLO: se lookback=4, usare serie [7,4,2,1] (4 pesi, ordine decrescente).
        'lookback_seasons'     => (int) env('PREDICTIVE_LOOKBACK_YEARS', 4),
        'season_decay_weights' => [7, 4, 2, 1],

        // ── Divisore fisso ─────────────────────────────────────────────────────
        // Denominatore costante per la posizione_media pesata.
        // CRITICO: mantenere a 17 — le stagioni mancanti contano come "0 contributo"
        // su 17 totali, penalizzando le squadre senza dati storici completi.
        // NON modificare: abbassarlo gonfia artificialmente il ranking delle provinciali.
        'fixed_divisor'        => 17,

        // ── Conversione Serie B ────────────────────────────────────────────────
        // Moltiplicatore per posizioni di Serie B. Sostituisce definitivamente
        // ogni logica additiva (+10 o +20).
        // Formula: score_B = score_punti_normalizzato * (CF * fixed_divisor / 10)
        //          = score * (0.95 * 17 / 10) = score * 1.615
        'serie_b_conversion_factor' => 0.95,

        // ── Modalità calcolo score ─────────────────────────────────────────────
        // true  → Punti Normalizzati:  score = (1 - pts/(played_games*3)) * 20
        //         Più preciso, neutralizza l'inerzia della posizione fissa.
        // false → Posizione raw (legacy, NON raccomandata)
        'use_points_mode'      => true,

        // ── Modulatori di contesto (post-ranking) ─────────────────────────────
        // Applicati sulla posizione_media prima di assegnare il tier finale.
        // Tier 1-2 (offensivo): 1.00x = nessun boost (confermato da ottimizzazione)
        // Tier 4-5 (difensivo): 1.10x = penalità lieve sulle squadre di bassa classifica
        'mod_tier_offensive'   => 1.00,  // Tier 1 & 2
        'mod_tier_defensive'   => 1.10,  // Tier 4 & 5

        // ── Soglie di assegnazione Tier ───────────────────────────────────────
        // Calibrate per il Points Mode (scala 0-20) DOPO applicazione modulatori.
        // NOTA: le soglie si confrontano con avg_mod (post-modulatore ×1.10 per T4-5).
        //
        // T1 ≤  6.5  → élite:    Inter(4.37), Napoli(5.69), Atalanta(6.35), Juve(6.48), Milan(6.49)
        // T2 ≤  8.5  → europa:   Roma(6.94), Lazio(7.07), Fiorentina(7.50), Bologna(7.60)
        // T3 ≤ 10.5  → mid:      Sassuolo(9.29), Torino(9.49), Genoa(10.03), Udinese(10.33)
        // T4 ≤ 12.5  → salvezza: Como(11.86*), Parma(12.12*), Verona(12.15*), Lecce(12.37*)
        //              *avg_mod = avg_raw × 1.10 (modulatore difensivo T4-5 già applicato)
        // T5 > 12.5  → fondo:    Cagliari(12.67*), Cremonese(13.05*), Pisa(13.18*)
        'tier_thresholds' => [
            1 =>  6.5,
            2 =>  8.5,
            3 => 10.5,
            4 => 12.5,
            // default → Tier 5 (avg_mod > 12.5)
        ],

    ],

];
