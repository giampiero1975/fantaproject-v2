<?php

use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 DEFINIZIONE QUERY 1: Roster Totale (Serie A)...\n";
    $rosterAgg = PlayerSeasonRoster::query()
        ->selectRaw('player_season_roster.season_id, COUNT(DISTINCT player_season_roster.player_id) as total_players')
        ->join('players', 'players.id', '=', 'player_season_roster.player_id')
        ->join('team_season', function($join) {
            $join->on('player_season_roster.team_id', '=', 'team_season.team_id')
                 ->on('player_season_roster.season_id', '=', 'team_season.season_id');
        })
        ->where('team_season.league_id', 1) // Serie A
        ->where(function($q) {
            $q->whereNull('players.deleted_at')
              ->orWhereNotNull('players.fanta_platform_id');
        })
        ->groupBy('player_season_roster.season_id');
    
    echo "🔍 DEFINIZIONE QUERY 2: Mappati (Serie A)...\n";
    $mappedAgg = PlayerSeasonRoster::query()
        ->selectRaw('player_season_roster.season_id, COUNT(DISTINCT player_season_roster.player_id) as mapped_players')
        ->join('players', 'players.id', '=', 'player_season_roster.player_id')
        ->join('team_season', function($join) {
            $join->on('player_season_roster.team_id', '=', 'team_season.team_id')
                 ->on('player_season_roster.season_id', '=', 'team_season.season_id');
        })
        ->where('team_season.league_id', 1) // Serie A
        ->where(function($q) {
            $q->whereNull('players.deleted_at')
              ->orWhereNotNull('players.fanta_platform_id');
        })
        ->whereNotNull('players.fbref_id')
        ->groupBy('player_season_roster.season_id');

    echo "\n🚀 ESECUZIONE TEST FINALE COMPLETO (JoinSub)...\n";
    $finalRows = Season::query()
        ->orderByDesc('season_year')
        ->leftJoinSub($rosterAgg, 'roster_agg', fn ($join) => $join->on('seasons.id', '=', 'roster_agg.season_id'))
        ->leftJoinSub($mappedAgg, 'mapped_agg', fn ($join) => $join->on('seasons.id', '=', 'mapped_agg.season_id'))
        ->take(3)
        ->get([
            'seasons.id',
            'seasons.season_year',
            DB::raw('COALESCE(roster_agg.total_players, 0) as total_players'),
            DB::raw('COALESCE(mapped_agg.mapped_players, 0) as mapped_players'),
        ]);

    foreach ($finalRows as $row) {
        echo "✅ Stagione {$row->season_year}: {$row->mapped_players}/{$row->total_players} players mappati.\n";
    }

    echo "\n🎉 TUTTI I TEST SUPERATI!\n";

} catch (\Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
