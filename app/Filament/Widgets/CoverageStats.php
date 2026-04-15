<?php

namespace App\Filament\Widgets;

use App\Models\Player;
use App\Models\HistoricalPlayerStat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CoverageStats extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $columns = 5;

    protected function getStats(): array
    {
        $lookbackSeasons = \App\Helpers\SeasonHelper::getLookbackSeasons();
        $stats = [];

        foreach ($lookbackSeasons as $year => $label) {
            $season = \App\Models\Season::where('season_year', $year)->first();
            if (!$season) continue;

            // Roster totale (inclusi venduti) per questa stagione
            $totalInRoster = \App\Models\PlayerSeasonRoster::where('season_id', $season->id)
                ->whereHas('player', fn($q) => $q->withTrashed())
                ->count();

            if ($totalInRoster === 0) continue;

            // Giocatori con stats in questa stagione
            $matchedPlayers = \App\Models\HistoricalPlayerStat::where('season_id', $season->id)
                ->distinct('player_id')
                ->count('player_id');

            $percentage = round(($matchedPlayers / $totalInRoster) * 100, 1);
            
            // Logica Colori
            $color = match(true) {
                $percentage >= 100 => 'success',
                $percentage > 0 => 'warning',
                default => 'gray',
            };

            // Logica Icone
            $icon = match(true) {
                $percentage >= 100 => 'heroicon-m-check-circle',
                default => 'heroicon-m-minus-circle',
            };

            $stats[] = Stat::make("Stagione {$label}", "{$percentage}%")
                ->description("{$matchedPlayers} / {$totalInRoster} Giocatori")
                ->color($color)
                ->icon($icon);
        }

        return $stats;
    }
}
