<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Console\Commands\Extraction\PlayersHistoricalSync;

$command = app(PlayersHistoricalSync::class);

$nameApi = "Amin Sarr";
$nameDb = "A. Sarr";

// Riflettiamo la logica di calculateSimilarity del comando
$score = $command->calculateSimilarity($nameApi, $nameDb);

echo "Matching Test:\n";
echo "  - API: $nameApi\n";
echo "  - DB: $nameDb\n";
echo "  - Risultato Score: $score%\n";
echo "  - Soglia richiesta: 78%\n";

if ($score >= 78) {
    echo "✅ MATCH RIUSCITO!\n";
} else {
    echo "❌ MATCH FALLITO!\n";
}
