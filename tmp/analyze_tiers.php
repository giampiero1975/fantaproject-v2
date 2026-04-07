<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use Illuminate\Support\Facades\DB;

$teams = Team::orderBy('tier_globale')->get();
echo str_pad("TEAM", 25) . " | " . str_pad("TIER 25/26", 12) . " | " . str_pad("POS 24/25", 10) . " | " . "AVG HIST\n";
echo str_repeat("-", 70) . "\n";

foreach ($teams as $t) {
    $standing = DB::table('team_historical_standings')
        ->where('team_id', $t->id)
        ->where('season_year', 2024)
        ->first();
        
    $pos2425 = $standing ? $standing->position : 'N/A';
    
    echo str_pad($t->name, 25) . " | " . 
         str_pad($t->tier_globale, 12, " ", STR_PAD_BOTH) . " | " . 
         str_pad($pos2425, 10, " ", STR_PAD_BOTH) . " | " . 
         $t->posizione_media_storica . "\n";
}
