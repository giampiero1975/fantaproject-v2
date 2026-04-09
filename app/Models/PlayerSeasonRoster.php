<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerSeasonRoster extends Model
{
    use HasFactory;

    protected $table = 'player_season_roster';

    protected $fillable = [
        'player_id',
        'season_id',
        'team_id',
        'parent_team_id',
        'role',
        'detailed_position',
        'initial_quotation',
        'current_quotation',
        'fvm',
    ];

    protected $casts = [
        'detailed_position' => 'array',
        'initial_quotation' => 'integer',
        'current_quotation' => 'integer',
        'fvm'               => 'integer',
        'player_id'         => 'integer',
        'season_id'         => 'integer',
        'team_id'           => 'integer',
        'parent_team_id'    => 'integer',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function parentTeam()
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }
}
