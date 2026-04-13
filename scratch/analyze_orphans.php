<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

$orphans = Player::whereNull('api_football_data_id')
    ->whereNotNull('fanta_platform_id')
    ->take(15)
    ->get();

echo "--- LISTA ORFANI CAMPIONE ---\n";
foreach ($orphans as $p) {
    echo "• {$p->name} | Squadra: " . ($p->team_name ?? 'N/A') . "\n";
}
