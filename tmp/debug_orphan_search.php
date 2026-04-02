<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;

echo "🕵️ DEBUG DATABASE TEAMS:\n";

$target = "Spezia";
$allNull = Team::whereNull('api_id')->get(['id', 'name', 'api_id']);

echo "📌 Team con api_id NULL trovati: " . $allNull->count() . "\n";
foreach ($allNull as $t) {
    echo "   - [{$t->id}] Nome: '{$t->name}'\n";
    if (stripos($t->name, $target) !== false) {
        echo "     🔍 SI! stripos() lo trova.\n";
    }
}

$like = Team::whereNull('api_id')->where('name', 'LIKE', '%' . $target . '%')->first();
echo "🔬 RISULTATO QUERY LIKE: " . ($like ? "TROVATO (ID: {$like->id})" : "NON TROVATO") . "\n";
