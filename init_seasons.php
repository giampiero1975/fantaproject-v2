<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;

$years = [2021, 2022, 2023, 2024, 2025];
foreach ($years as $year) {
    Season::updateOrCreate(
        ['season_year' => $year],
        [
            'id' => match($year) {
                2021 => 1801, // Mock IDs or keep previous
                2022 => 1802,
                2023 => 1803,
                2024 => 1804,
                2025 => 1805,
                default => null
            },
            'start_date' => $year . '-08-20',
            'end_date' => ($year + 1) . '-05-25',
            'is_current' => ($year == 2025),
        ]
    );
}

echo "Seasons 2021-2025 initialized.\n";
