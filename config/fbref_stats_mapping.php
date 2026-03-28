<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Mappatura Metriche Universali -> Colonne FBref Database
     |--------------------------------------------------------------------------
     |
     | Questa mappa collega il nome della "Metrica Universale" (usata nella
     | tabella config_percentile_ranks e nel calcolo dei pesi) al nome reale
     | della colonna nella tabella `player_fbref_stats`.
     |
     */
    
    'universal_to_column' => [
        // --- PORTIERI ---
        'stats_keeper_gk_saves'             => 'gk_saves',
        'stats_keeper_gk_pens_saved'        => 'gk_penalties_saved',
        // Nota: gk_goals_against_per90 è calcolato, ma mappiamo la colonna base per riferimento
        'stats_keeper_gk_goals_against_per90' => 'gk_goals_against',
        'stats_keeper_gk_shots_on_target_against' => 'gk_shots_on_target_against',
        'stats_keeper_gk_save_pct'          => 'gk_save_percentage',
        'stats_keeper_gk_clean_sheets_pct'  => 'gk_cs_percentage', // Era gk_clean_sheets (volume), ora usiamo pct
        
        // --- MOVIMENTO & ALTRI ---
        'stats_gca_sca'                     => 'sca',
        'stats_defense_errors'              => 'errors',
        'stats_misc_aerials_won_pct'        => 'aerials_won_pct',
        'stats_defense_clearances'          => 'clearances',
        'stats_possession_touches_def_pen_area' => 'touches_def_pen_area',
        
        // --- PASSAGGI (Nomi corretti secondo lo schema DB) ---
        'stats_passing_passes_pct'          => 'passes_pct',
        'stats_passing_passes_progressive_distance' => 'passes_progressive_distance',
        'stats_passing_passes_into_final_third' => 'passes_into_final_third',
        'stats_passing_passes_pct_long'     => 'passes_pct_long',
        
        // --- DIFESA (Nuovi Parametri) ---
        'stats_defense_tackles_won'         => 'defense_tackles_won',
        'stats_defense_interceptions'       => 'defense_interceptions',
        'stats_defense_blocks'              => 'defense_blocks_general',
        'stats_defense_clearances'          => 'defense_clearances',
        'stats_misc_aerials_won'            => 'aerials_won',
        'stats_defense_recoveries'          => 'defense_recoveries', // Opzionale ma utile
    ],
    /*
     |--------------------------------------------------------------------------
     | Mappatura Backfill: Colonna DB <= Percorso JSON
     |--------------------------------------------------------------------------
     | Definisce dove pescare i dati nel campo JSON 'data_team' per riempire
     | le colonne mancanti. Supporta array per tentativi multipli (fallback).
     */
    'backfill_map' => [
        // --- COLONNE BASE (Il Fix per Carnesecchi 2022) ---
        'gk_saves'                      => ['stats_keeper.gk_saves'],
        'gk_goals_against'              => ['stats_keeper.gk_goals_against'],
        'gk_shots_on_target_against'    => ['stats_keeper.gk_shots_on_target_against'],
        
        // --- PORTIERI ---
        'gk_save_percentage'            => ['stats_keeper.gk_save_pct'],
        'gk_cs_percentage'              => ['stats_keeper.gk_clean_sheets_pct'],
        
        // Fallback intelligente: prova la chiave nuova, se manca prova la vecchia
        'gk_penalties_saved'            => ['stats_keeper.gk_penalties_saved', 'stats_keeper.gk_pens_saved'],
        
        'crosses_stopped_pct'           => ['stats_keeper.crosses_stopped_pct'],
        'gk_sweeper_opa'                => ['stats_keeper.gk_sweeper_opa'],
        
        // --- MISC & ALTRI ---
        'aerials_won_pct'               => ['stats_misc.aerials_won_pct'],
        'errors'                        => ['stats_defense.errors'],
        'clearances'                    => ['stats_defense.clearances'],
        'touches_def_pen_area'          => ['stats_possession.touches_def_pen_area'],
        
        // --- PASSAGGI ---
        'passes_pct'                    => ['stats_passing.passes_pct'],
        'passes_progressive_distance'   => ['stats_passing.passes_progressive_distance'],
        'passes_into_final_third'       => ['stats_passing.passes_into_final_third'],
        'passes_pct_long'               => ['stats_passing.passes_pct_long'],
        
        // --- DIFESA (Recupero Dati Mancanti) ---
        'defense_tackles_won'           => ['stats_defense.tackles_won'],
        'defense_interceptions'         => ['stats_defense.interceptions'],
        'defense_clearances'            => ['stats_defense.clearances'],
        'defense_blocks_general'        => ['stats_defense.blocks'],
        'aerials_won'                   => ['stats_misc.aerials_won'],
        'defense_recoveries'            => ['stats_defense.balls_recovered'],
    ],
];