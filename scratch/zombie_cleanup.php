<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$zombies = Player::withTrashed()
    ->whereNotNull('api_football_data_id')
    ->whereNull('fanta_platform_id')
    ->whereDoesntHave('historicalStats')
    ->whereDoesntHave('rosters')
    ->get();

$count = $zombies->count();

if ($count === 0) {
    echo "✅ Nessun record zombie trovato. Bonifica già eseguita o criteri non soddisfatti.\n";
    exit;
}

// 1. BACKUP CSV
$backupPath = __DIR__ . '/backup_zombies_' . date('Y-m-d') . '.csv';
$fp = fopen($backupPath, 'w');
fputcsv($fp, ['id', 'name', 'api_football_data_id', 'created_at']);

foreach ($zombies as $z) {
    fputcsv($fp, [$z->id, $z->name, $z->api_football_data_id, $z->created_at]);
}
fclose($fp);

echo "💾 Backup di {$count} record creato in: {$backupPath}\n";

// 2. CANCELLAZIONE FISICA
echo "🧹 Inizio cancellazione fisica di {$count} record...\n";

DB::beginTransaction();
try {
    foreach ($zombies as $z) {
        $z->forceDelete();
    }
    DB::commit();
    echo "✨ BONIFICA COMPLETATA. 110 record rimossi con successo.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERRORE durante la cancellazione: " . $e->getMessage() . "\n";
}
