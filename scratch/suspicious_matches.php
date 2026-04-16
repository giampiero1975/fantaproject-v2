<?php

use App\Models\Player;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prendiamo i player aggiornati oggi (o con FBref ID ma che puzzano)
$recentPlayers = Player::withTrashed()
    ->whereNotNull('fbref_id')
    ->where('updated_at', '>=', now()->startOfDay())
    ->get();

echo "🔍 ANALISI SOSPÈTTI MATCH FBREF (Oggi)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$suspicious = [];

foreach ($recentPlayers as $p) {
    // Estraiamo il nome probabile dall'URL FBref 
    // Format: .../players/id/Nome-Cognome
    $urlName = '';
    $urlParts = explode('/', rtrim($p->fbref_url, '/'));
    $urlNameRaw = end($urlParts); // Prende l'ultima parte
    $urlName = str_replace(['-', 'Stats'], ' ', $urlNameRaw);
    $urlName = trim(preg_replace('/\s+/', ' ', $urlName)); // Pulisce spazi doppi

    $localTokens = array_filter(explode(' ', strtolower(Str::ascii($p->name))));
    $urlTokens = array_filter(explode(' ', strtolower(Str::ascii($urlName))));

    $isSuspicious = false;
    $reason = '';

    // Caso 1: Nome locale molto corto (Nani, Bruno)
    if (count($localTokens) === 1 && strlen($localTokens[0]) <= 5) {
        $isSuspicious = true;
        $reason = "Nome locale troppo corto (1 token, <= 5 char)";
    }

    // Caso 2: Discrepanza forte tra numero di parole
    if (abs(count($localTokens) - count($urlTokens)) >= 2) {
        $isSuspicious = true;
        $reason = "Forte discrepanza numero token (" . count($localTokens) . " vs " . count($urlTokens) . ")";
    }

    // Caso 3: Nessun token del nome locale è contenuto interamente nel nome URL (esclusi iniziali)
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

    if (!$fullMatch) {
        $isSuspicious = true;
        $reason = "Nessun match di parola intera trovata";
    }

    if ($isSuspicious) {
        $suspicious[] = [
            'id' => $p->id,
            'name' => $p->name,
            'url_name' => $urlName,
            'reason' => $reason,
            'url' => $p->fbref_url
        ];
    }
}

foreach ($suspicious as $s) {
    echo "⚠️ [ID: {$s['id']}] {$s['name']} -> FBref: {$s['url_name']}\n";
    echo "   Motivo: {$s['reason']}\n";
    echo "   URL:    {$s['url']}\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Totale Sospetti: " . count($suspicious) . " su " . $recentPlayers->count() . " analizzati.\n";
