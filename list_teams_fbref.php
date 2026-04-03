<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;

foreach (Team::all() as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | Short: {$t->short_name} | FBref ID: " . ($t->fbref_id ?: 'NULL') . "\n";
}
