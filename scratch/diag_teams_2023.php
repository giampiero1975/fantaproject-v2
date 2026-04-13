<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$season = \App\Models\Season::where('season_year', 2023)->first();
echo "Season Year: 2023 (ID: " . ($season->id ?? 'NOT FOUND') . ")\n";

$teams = \App\Models\Team::whereHas('seasons', function($q) use ($season) {
    if ($season) $q->where('season_id', $season->id);
})->get(['id', 'name', 'api_id']);

echo "Numero squadre trovate per il 2023: " . $teams->count() . "\n";
foreach ($teams as $t) {
    echo "- " . $t->name . " (ID: " . $t->id . ", API_ID: " . ($t->api_id ?? 'NULL') . ")\n";
}
