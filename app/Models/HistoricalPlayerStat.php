<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalPlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_fanta_platform_id',
        'player_id',
        'season_id',
        'team_id',
        'games_played',
        'average_rating',
        'fanta_average',
        'goals',
        'goals_conceded',
        'penalties_saved',
        'penalties_taken',
        'penalties_scored',
        'penalties_missed',
        'assists',
        'assists_from_set_piece',
        'yellow_cards',
        'red_cards',
        'own_goals',
        'xg',
        'xg_assist',
        'gca',
        'passes_progressive_distance',
        'passes_into_final_third',
        'defense_tackles_won',
        'defense_blocks',
        'defense_interceptions',
        'defense_recoveries',
        'aerials_won',
        'touches_def_pen_area',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'fanta_average' => 'float',
        'goals' => 'float',
        'goals_conceded' => 'float',
        'penalties_saved' => 'float',
        'penalties_taken' => 'float',
        'penalties_scored' => 'float',
        'penalties_missed' => 'float',
        'assists' => 'float',
        'yellow_cards' => 'float',
        'red_cards' => 'float',
        'own_goals' => 'float',
        'xg' => 'float',
        'xg_assist' => 'float',
        'gca' => 'float',
        'passes_progressive_distance' => 'float',
        'passes_into_final_third' => 'float',
        'defense_tackles_won' => 'float',
        'defense_blocks' => 'float',
        'defense_interceptions' => 'float',
        'defense_recoveries' => 'float',
        'aerials_won' => 'float',
        'touches_def_pen_area' => 'float',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function season()
    {
        return $this->belongsTo(Season::class, 'season_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
