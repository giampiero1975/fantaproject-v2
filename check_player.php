<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

$p = Player::where('name', 'like', '%Gregorio%')->first();
if ($p) {
    echo "Player: " . $p->name . " (ID: " . $p->id . ")\n";
    echo "Registry Team ID: " . ($p->team_id ?? 'NULL') . "\n";
    echo "Registry Team Name: " . ($p->team_name ?? 'NULL') . "\n";
    echo "\nROSTERS:\n";
    foreach ($p->rosters as $r) {
        echo "Season: " . $r->season_id . " | Team: " . ($r->team?->short_name ?? 'N/A') . " (" . ($r->team_id ?? 'NULL') . ")\n";
    }
} else {
    echo "Player not found.\n";
}
