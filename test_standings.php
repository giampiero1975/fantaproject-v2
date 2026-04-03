<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\FbrefScrapingService;

$service = app(FbrefScrapingService::class);
$standings = $service->scrapeSerieAStandings();

if (empty($standings)) {
    echo "FAILED to scrape standings\n";
} else {
    echo "SUCCESS: Found " . count($standings) . " teams\n";
    foreach ($standings as $s) {
        echo "- {$s['fbref_name']} (ID: {$s['fbref_id']})\n";
    }
}
