<?php

use App\Models\Team;
use App\Models\Season;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = Team::where('name', 'like', '%Lazio%')->first();
if ($t) {
    echo "Lazio ID trovato: " . $t->id . "\n";
    echo "URL mappata: " . $t->fbref_url . "\n";
} else {
    echo "Lazio non trovata.\n";
}
