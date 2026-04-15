<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

$names = ['Sportiello', 'Kalulu', 'Romagnoli', 'Terracciano', 'Gabbia', 'De Winter', 'Maignan', 'Tomori'];

echo "--- Player and Roster Audit ---\n";

foreach ($names as $name) {
    $player = Player::where('name', 'like', "%{$name}%")->withTrashed()->get();
    
    foreach ($player as $p) {
        echo "\nPlayer: [{$p->id}] {$p->name} | Roles: " . json_encode($p->detailed_position) . " | Trashed: " . ($p->trashed() ? 'YES' : 'NO') . "\n";
        
        $rosters = $p->rosters()->with(['team', 'season'])->get();
        echo "Found " . $rosters->count() . " roster records:\n";
        foreach ($rosters as $r) {
            echo " - [{$r->id}] Season: " . ($r->season->season_year ?? 'N/A') . " (ID: {$r->season_id}) | Team: " . ($r->team->name ?? 'N/A') . " (ID: {$r->team_id})\n";
        }
        
        $latest = $p->latestRoster;
        echo "Latest Roster (calculated): " . ($latest ? "[{$latest->id}] Season ID: {$latest->season_id} | Team: " . ($latest->team->name ?? 'N/A') : 'NONE') . "\n";
    }
}
