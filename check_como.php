<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use Illuminate\Support\Facades\DB;

$team = Team::where('name', 'LIKE', '%Como%')->first();
if (!$team) {
    echo "Team Como not found.\n";
    exit;
}

echo "Team ID: " . $team->id . " (" . $team->name . ")\n";
$standings = DB::table('team_historical_standings')
    ->where('team_id', $team->id)
    ->get();

foreach ($standings as $s) {
    echo "Season: " . $s->season_year . " | League: " . $s->league_name . " | Pos: " . $s->position . " | Pts: " . $s->points . " | Played: " . ($s->played_games ?? $s->played ?? 'N/A') . "\n";
}
