<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Query base: Ha FantaID ma NON ha API ID
$baseQuery = Player::withTrashed()
    ->whereNotNull('fanta_platform_id')
    ->whereNull('api_football_data_id');

$total = $baseQuery->count();
$active = (clone $baseQuery)->whereNull('deleted_at')->count();
$deleted = (clone $baseQuery)->whereNotNull('deleted_at')->count();

// Analisi Patrimonio Storico
$withStats = (clone $baseQuery)->whereHas('historicalStats')->count();
$withRoster = (clone $baseQuery)->whereHas('rosters')->count();

echo "📊 ANALISI CALCIATORI SCOLLEGATI (FantaID senza API)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Totale record: {$total}\n";
echo "  - Attivi: {$active}\n";
echo "  - Cancellati (Soft-Delete): {$deleted}\n";
echo "\nPatrimonio Dati:\n";
echo "  - Con Statistiche Storiche: {$withStats}\n";
echo "  - Presenti in almeno una Rosa: {$withRoster}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($total > 0) {
    echo "\nCAMPIONE (Cancellati con Stats):\n";
    $samples = (clone $baseQuery)
        ->whereNotNull('deleted_at')
        ->whereHas('historicalStats')
        ->with('historicalStats')
        ->take(15)
        ->get();

    foreach ($samples as $s) {
        $seasons = $s->historicalStats->pluck('season_year')->unique()->sort()->implode(', ');
        echo "- [ID: {$s->id}] {$s->name} | Stagioni Stats: [{$seasons}]\n";
    }
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
