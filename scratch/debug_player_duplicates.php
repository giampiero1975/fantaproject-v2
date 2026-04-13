<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

$names = ['Ruggeri', 'Mancini', 'Ikoné', 'Ikone'];

echo "--- ANALISI PLAYER DUPLICATES ---\n";
foreach ($names as $search) {
    echo "🔍 Ricerca per: $search\n";
    $players = Player::where('name', 'like', "%$search%")->get();
    foreach ($players as $p) {
        echo "   [ID: {$p->id}] Name: {$p->name} | API ID: " . ($p->api_id ?? 'NULL') . " | FBref: " . ($p->fbref_id ?? 'NULL') . "\n";
    }
    echo "\n";
}
echo "--- FINE ANALISI ---\n";
