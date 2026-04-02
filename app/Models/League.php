<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class League extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'country_code', 'api_id'];

    public function teamSeasons()
    {
        return $this->hasMany(TeamSeason::class);
    }
}
