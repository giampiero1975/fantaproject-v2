<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$seasonId = 1;
$teams = App\Models\Team::whereHas('teamSeasons', fn($q) => $q->where('season_id', $seasonId)->where('league_id', 1))->get();

$report = [];
foreach ($teams as $team) {
    $total = App\Models\PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $seasonId)->count();
    $missing = App\Models\PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $seasonId)
        ->whereHas('player', fn($q) => $q->whereNull('fbref_id'))->count();
    
    $pct = $total > 0 ? ($missing / $total) * 100 : 0;
    $report[] = [
        'team' => $team->name,
        'total' => $total,
        'missing' => $missing,
        'pct' => round($pct, 2)
    ];
}

usort($report, fn($a, $b) => $b['pct'] <=> $a['pct']);
print_r($report);
