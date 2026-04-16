<?php

use App\Models\Player;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prendiamo la stessa logica dello script di diagnostica
$recentPlayers = Player::withTrashed()
    ->whereNotNull('fbref_id')
    ->where('updated_at', '>=', now()->startOfDay())
    ->get();

$toRollback = [];

foreach ($recentPlayers as $p) {
    $urlParts = explode('/', rtrim($p->fbref_url, '/'));
    $urlNameRaw = end($urlParts);
    $urlName = str_replace(['-', 'Stats'], ' ', $urlNameRaw);
    $urlName = trim(preg_replace('/\s+/', ' ', $urlName));

    $localTokens = array_filter(explode(' ', strtolower(Str::ascii($p->name))));
    $urlTokens = array_filter(explode(' ', strtolower(Str::ascii($urlName))));

    $isSuspicious = false;

    // Caso 1: Nome locale troppo corto
    if (count($localTokens) === 1 && strlen($localTokens[0]) <= 5) {
        $isSuspicious = true;
    }
    // Caso 2: Discrepanza forte numero token
    if (abs(count($localTokens) - count($urlTokens)) >= 2) {
        $isSuspicious = true;
    }
    // Caso 3: Nessun match di parola intera
    $fullMatch = false;
    foreach ($localTokens as $lt) {
        if (strlen($lt) > 1) {
            foreach ($urlTokens as $ut) {
                if (str_contains($ut, $lt) || str_contains($lt, $ut)) {
                    $fullMatch = true;
                    break 2;
                }
            }
        }
    }
    if (!$fullMatch) $isSuspicious = true;

    if ($isSuspicious) {
        $toRollback[] = $p;
    }
}

echo "🧹 ESECUZIONE ROLLBACK CHIRURGICO...\n";
foreach ($toRollback as $p) {
    echo "♻️ Resetting: {$p->name} (#{$p->id}) - era mappato a " . $p->fbref_url . "\n";
    $p->fbref_id = null;
    $p->fbref_url = null;
    $p->save();
}

echo "\n✅ Operazione completata. " . count($toRollback) . " record resettati.\n";
