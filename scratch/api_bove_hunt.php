<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$svc = app(App\Services\TeamDataService::class);

echo "--- 🔍 INVESTIGAZIONE API BOVE (2024) ---\n";

$romaId = 100;
$fiorentinaId = 99;

$romaSquad = $svc->getSquad($romaId, 2024);
$fioSquad = $svc->getSquad($fiorentinaId, 2024);

$findBove = function($squad, $teamName) {
    foreach ($squad as $p) {
        if (str_contains($p['name'], 'Bove')) {
            echo "✅ Trovato Bove in API {$teamName} (ID: {$p['id']})\n";
            return true;
        }
    }
    return false;
};

if (!$findBove($romaSquad, 'ROMA')) echo "❌ Bove NON trovato in ROSA ROMA via API.\n";
if (!$findBove($fioSquad, 'FIORENTINA')) echo "❌ Bove NON trovato in ROSA FIORENTINA via API.\n";

echo "--- 🏁 FINE ---";
