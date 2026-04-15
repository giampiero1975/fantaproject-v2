<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

$counts = PlayerSeasonRoster::selectRaw('season_id, count(*) as c')
    ->whereNotNull('parent_team_id')
    ->whereColumn('parent_team_id', '!=', 'team_id')
    ->groupBy('season_id')
    ->get();

echo "--- CONTEGGIO PRESTITI PER STAGIONE ---\n";
foreach ($counts as $row) {
    echo "Season ID {$row->season_id}: {$row->c} prestiti\n";
}
echo "---------------------------------------\n";
