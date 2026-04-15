<?php

/**
 * Diagnostic Script: Check FBref Connection & Extraction
 * Purpose: Verify if ScraperAPI (with country_code=it) can fetch Venezia 2021/22 stats without Status 500.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ProxyManagerService;
use App\Traits\ManagesFbrefScraping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FbrefDiagnostic {
    use ManagesFbrefScraping;

    public function runTest(string $url) {
        echo "Starting test for URL: $url\n";
        echo "Proxy Provider check...\n";
        
        try {
            $crawler = $this->fetchPageWithProxy($url);
            echo "SUCCESS: Page fetched.\n";
            
            $table = $crawler->filter('table#stats_standard');
            if ($table->count() > 0) {
                echo "SUCCESS: table#stats_standard found.\n";
                $rows = $table->filter('tbody > tr')->count();
                echo "Number of players found in table: $rows\n";
                
                // Print first 3 players for verification
                $tableData = $this->scrapeTable($crawler, 'stats_standard', [
                    'player' => 'Player',
                    'goals' => 'Gls',
                    'assists' => 'Ast'
                ]);
                
                echo "Sample Data (First 3):\n";
                print_r(array_slice($tableData, 0, 3));
            } else {
                echo "FAILURE: table#stats_standard NOT FOUND.\n";
                $this->saveDebugHtml($crawler, 'venezia_2021_fail');
                echo "HTML saved to storage/app/debug_html for inspection.\n";
            }
            
        } catch (\Exception $e) {
            echo "EXCEPTION: " . $e->getMessage() . "\n";
        }
    }
}

$tester = new FbrefDiagnostic();
// Venezia 2021-2022 URL
$url = "https://fbref.com/en/squads/af5d5982/2021-2022/Venezia-Stats";
$tester->runTest($url);
