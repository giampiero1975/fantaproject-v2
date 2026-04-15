<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;

echo "--- SEASONS LIST ---\n";
foreach (Season::all() as $s) {
    echo "ID: {$s->id} | Label: {$s->label} | Year: {$s->year}\n";
}

if (Season::where('year', 2021)->exists()) {
    echo "\n2021 Season FOUND!\n";
} else {
    echo "\n2021 Season MISSING!\n";
}
