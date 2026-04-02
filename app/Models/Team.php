<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',                   // Nome comune (Inter, Milan)
        'short_name',             // Nome breve (Inter)
        'tla',                    // Acronimo (INT)
        'logo_url',               // URL dello stemma (era crest_url)
        'api_id',                 // ID ufficiale football-data (era api_football_data_id)
        'fbref_id',               // ID per mappatura FBref
        'fbref_url',              // URL per mappatura FBref
        'posizione_media_storica',// Classifica storica (era posizione_media)
        'tier_globale',           // Previsione forza complessiva (era tier)
        'fanta_platform_id',      // ID piattaforma Fanta (Gazzetta)
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'api_id' => 'integer',
        'tier_globale' => 'integer',
        'fanta_platform_id' => 'integer',
        'posizione_media_storica' => 'float',
    ];
    
    protected $guarded = [];

    // -- Relazioni -------------------------------------------------------------

    public function teamSeasons()
    {
        return $this->hasMany(TeamSeason::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }
    
    public function historicalStandings()
    {
        return $this->hasMany(TeamHistoricalStanding::class);
    }
    
    public function playerFbrefStats()
    {
        return $this->hasMany(PlayerFbrefStat::class);
    }
}