<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_id',
        'fbref_id',
        'start_date',
        'end_date',
        'season_year',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'season_year' => 'integer',
    ];

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_season', 'season_id', 'team_id')
            ->withTimestamps();
    }
}
