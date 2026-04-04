<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\LeagueHistoryScraperService;
use App\Models\League;
use Illuminate\Support\Facades\Log;

$service = app(LeagueHistoryScraperService::class);
$leagueB = League::where('fbref_id', '18')->first();

foreach ([2022, 2023, 2024] as $year) {
    echo "--- Debugging Serie B ($year) ---\n";
    $result = $service->scrapeSeason($year, true, $leagueB);
    print_r($result);
    echo "---------------------------\n";
}
