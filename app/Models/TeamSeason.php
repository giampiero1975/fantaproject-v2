<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamSeason extends Model
{
    use HasFactory;

    protected $table = 'team_season';

    protected $fillable = [
        'team_id',
        'season_id',
        'league_id',
        'tier_stagionale',
        'posizione_finale',
        'punti',
    ];

    protected $casts = [
        'punti'     => 'integer',
        'posizione_finale' => 'integer',
        'tier_stagionale'  => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function league()
    {
        return $this->belongsTo(League::class);
    }
}
