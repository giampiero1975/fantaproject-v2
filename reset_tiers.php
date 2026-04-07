<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \App\Models\Team::query()->update(['tier_globale' => null]);
    echo "Tiers azzerati con successo!\n";
} catch (\Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
