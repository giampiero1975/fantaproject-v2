<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerFbrefStat extends Model
{
    use HasFactory, SoftDeletes;
    
    /**
     * Il nome della tabella associata al modello.
     * @var string
     */
    protected $table = 'player_fbref_stats';
    
    /**
     * MODIFICA DEFINITIVA: L'array ora contiene TUTTE e SOLE le colonne
     * della tua tabella, con i nomi ESATTI, permettendo il salvataggio.
     */
    protected $fillable = [
        // Chiavi
        'player_id',
        'team_id',
        'season_year',
        'league_name',
        'data_source',
        
        // Dati Anagrafici (come da tua describe)
        'position',
        'age',
        
        // Statistiche di Base
        'games',
        'games_starts',
        'minutes',
        'minutes_90s',
        'goals',
        'assists',
        'goals_assists',
        'goals_pens',
        'pens_made',
        'pens_att',
        'cards_yellow',
        'cards_red',
        
        // Statistiche Avanzate
        'xg',
        'npxg',
        'xg_assist',
        'npxg_xg_assist',
        'progressive_carries',
        'progressive_passes',
        'progressive_passes_received',
        
        // Statistiche "Per 90"
        'goals_per90',
        'assists_per90',
        'goals_assists_per90',
        'goals_pens_per90',
        'goals_assists_pens_per90',
        'xg_per90',
        'xg_assist_per90',
        'xg_xg_assist_per90',
        'npxg_per90',
        'npxg_xg_assist_per90',
        
        // Altre Statistiche
        'sca',
        'gca',
        'shots',
        'shots_on_target',
        'fouls',
        'fouled',
        'own_goals',
        
        // Statistiche Portiere
        'gk_goals_against',
        'gk_shots_on_target_against',
        'gk_saves',
        'gk_save_pct',
        'gk_clean_sheets',
        'gk_clean_sheets_pct',
        'gk_pens_att',
        'gk_pens_allowed',
        'gk_pens_saved',
        
        // Campi JSON
        'data',
        'data_team',
    ];
    
    /**
     * NESSUNA MODIFICA: La tua gestione del casting JSON è preservata.
     */
    protected $casts = [
        'data' => 'array',
        'data_team' => 'array',
    ];
    
    /**
     * NESSUNA MODIFICA: La tua relazione con il modello Player è preservata.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    /**
     * NESSUNA MODIFICA: La tua relazione con il modello Team è preservata.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}