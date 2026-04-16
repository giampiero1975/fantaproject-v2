<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fbref_url',
        'fbref_id',
        'fanta_platform_id',
        'api_football_data_id',
        'parent_team_id',
        'name',
        'role',
        'detailed_position',
        'date_of_birth',
    ];

    protected $casts = [
        'detailed_position' => 'array',
        'date_of_birth'     => 'date',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    /**
     * Ritorna gli storici di quadra/ruolo/quotazioni per stagione.
     */
    public function rosters()
    {
        return $this->hasMany(PlayerSeasonRoster::class);
    }

    /**
     * Ritorna il roster della stagione più recente.
     */
    public function latestRoster()
    {
        // Nel nostro DB le stagioni recenti hanno ID minori (es. 2025 = ID 1)
        // ofMany garantisce che SQL restituisca sempre e solo un record atomico per le subquery.
        return $this->hasOne(PlayerSeasonRoster::class)->ofMany([
            'season_id' => 'min',
            'id' => 'max',
        ]);
    }

    /**
     * Ritorna il roster relativo a una specifica stagione.
     */
    public function rosterForSeason(int|Season $seasonId)
    {
        if ($seasonId instanceof Season) {
            $seasonId = $seasonId->id;
        }

        return $this->rosters()->where('season_id', $seasonId)->first();
    }

    /**
     * Ritorna la squadra proprietaria del cartellino (Anagrafica).
     */
    public function parentTeam()
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    // ─── Accessors (Virtual Attributes) ─────────────────────────────────────

    /**
     * Accessor per recuperare il team_id corrente senza colonna fisica.
     * Uso: $player->team_id
     */
    public function getTeamIdAttribute(): ?int
    {
        return $this->latestRoster?->team_id;
    }

    /**
     * Accessor per recuperare il nome del team corrente.
     * Uso: $player->team_name
     */
    public function getTeamNameAttribute(): ?string
    {
        return $this->latestRoster?->team?->name;
    }

    /**
     * Ritorna gli storici delle statistiche per questo calciatore.
     */
    public function historicalStats()
    {
        return $this->hasMany(HistoricalPlayerStat::class, 'player_id');
    }
}
