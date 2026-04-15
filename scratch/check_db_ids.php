<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "--- DATABASE ID AUDIT ---\n";

// 1. Check Maignan specifically
$p = Player::where('name', 'like', '%Maignan%')->first();
if ($p) {
    echo "Found Maignan in DB:\n";
    echo " - Internal ID: {$p->id}\n";
    echo " - Fanta Platform ID: " . ($p->fanta_platform_id ?? 'NULL') . "\n";
    echo " - Row Data: " . json_encode($p->toArray()) . "\n";
} else {
    echo "Maignan NOT FOUND in players table!\n";
}

// 2. Sample first 10 players with Fanta ID
echo "\nFirst 10 players with Fanta ID:\n";
$players = Player::whereNotNull('fanta_platform_id')->limit(10)->get();
foreach ($players as $player) {
    echo " - {$player->name} (ID: {$player->fanta_platform_id})\n";
}

// 3. Count players with NULL Fanta ID
$countNull = Player::whereNull('fanta_platform_id')->orWhere('fanta_platform_id', '')->count();
echo "\nTotal players with NULL/Empty Fanta ID: $countNull / " . Player::count() . "\n";

// 4. Try to match ID 4312 directly
$match4312 = Player::where('fanta_platform_id', 4312)->first();
if ($match4312) {
    echo "\nDIRECT MATCH for 4312: {$match4312->name}\n";
} else {
    echo "\nNO PLAYER has Fanta ID 4312 in DB!\n";
}
