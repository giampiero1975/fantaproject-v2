<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$seasonId = 1;

$teams = \App\Models\Team::whereHas('teamSeasons', function($q) use ($seasonId) {
    $q->where('season_id', $seasonId)->where('league_id', 1);
})->get();

$report = [];
foreach ($teams as $team) {
    $total = \App\Models\PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $seasonId)->count();
    $missing = \App\Models\PlayerSeasonRoster::where('team_id', $team->id)->where('season_id', $seasonId)
        ->whereHas('player', function($q) {
            $q->whereNull('fbref_id');
        })->count();
    
    if ($total > 0) {
        $pct = ($missing / $total) * 100;
        $report[] = [
            'team' => $team->name,
            'total' => $total,
            'missing' => $missing,
            'pct' => round($pct, 2)
        ];
    }
}

usort($report, fn($a, $b) => $b['pct'] <=> $a['pct']);

echo "CLASSIFICA SQUADRE COLABRODO (Stagione ID {$seasonId})\n";
echo str_repeat("=", 50) . "\n";
foreach ($report as $row) {
    printf("%-20s | Missing: %2d/%2d | Coverage: %5.2f%%\n", $row['team'], $row['missing'], $row['total'], 100 - $row['pct']);
}
