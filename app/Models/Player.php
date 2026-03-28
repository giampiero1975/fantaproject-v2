<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'parent_team_id',
        'fbref_url',
        'fanta_platform_id',
        'api_football_data_id',
        'name',
        'team_name',
        'role',
        'initial_quotation',
        'current_quotation',
        'fvm',
        'date_of_birth',
        'detailed_position',
    ];

    protected $casts = [
        'detailed_position' => 'array',
        'date_of_birth'     => 'date',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function parentTeam()
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }
}
