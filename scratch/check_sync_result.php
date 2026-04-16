<?php

use App\Models\Player;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🏁 VERIFICA RISULTATI SYNC MASSIVO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$k = Player::withTrashed()->find(70);
$r = Player::withTrashed()->find(218);

echo "⭐ Koulibaly (ID 70): " . ($k->fbref_id ?: '❌ ANCORA NULL') . " (" . ($k->fbref_url ?: 'Nessun URL') . ")\n";
echo "⭐ Radu (ID 218):     " . ($r->fbref_id ?: '❌ ANCORA NULL') . " (" . ($r->fbref_url ?: 'Nessun URL') . ")\n";

echo "\nControllo altri big del 2021/22:\n";
$bigs = [
    3 => 'Ospina',
    457 => 'Insigne',
    445 => 'Mertens',
    286 => 'Kessie'
];

foreach ($bigs as $id => $name) {
    $p = Player::withTrashed()->find($id);
    if ($p) {
        echo "- $name (ID $id): " . ($p->fbref_id ?: '❌ NULL') . "\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
