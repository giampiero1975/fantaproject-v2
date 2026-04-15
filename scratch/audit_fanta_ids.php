<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

$total = Player::count();
$withFantaId = Player::whereNotNull('fanta_platform_id')->where('fanta_platform_id', '>', 0)->count();

echo "Total Players: $total\n";
echo "Players with Fanta ID: $withFantaId\n";

$osimhen = Player::where('name', 'like', '%Osimhen%')->first();
if ($osimhen) {
    echo "Osimhen Fanta ID in DB: " . ($osimhen->fanta_platform_id ?? 'NULL') . "\n";
} else {
    echo "Osimhen not found in DB\n";
}

$sample = Player::whereNull('fanta_platform_id')->orWhere('fanta_platform_id', 0)->limit(5)->get();
echo "\nSample of players WITHOUT Fanta ID:\n";
foreach ($sample as $s) {
    echo "- {$s->name} (Role: {$s->role})\n";
}
