<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ProxyService;
use App\Services\ProxyManagerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Foundation\Testing\RefreshDatabase;

class ProxyIntensiveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_zenrows_with_spezia_2022()
    {
        // Creiamo il record nel database di test (SQLite in-memory)
        $proxy = ProxyService::create([
            'id' => 7,
            'name' => 'ZenRows',
            'slug' => 'zenrows',
            'base_url' => 'https://api.zenrows.com/v1',
            'api_key' => 'd9b8be2a7a1eecdcedeaccaa68305900c3e52bfe5', // Chiave reale decriptata
            'limit_monthly' => 1000,
            'current_usage' => 0,
            'is_active' => true,
            'priority' => 1,
            'js_cost' => 2,
        ]);

        $targetUrl = 'https://fbref.com/en/squads/82110ea4/2022-2023/Spezia-Stats';
        $manager = app(ProxyManagerService::class);
        
        echo "\n[DEBUG] Testing Proxy: {$proxy->name} (Target: {$targetUrl})\n";
        
        $proxyUrl = $manager->getProxyUrl($proxy, $targetUrl);
        echo "[DEBUG] Generated Proxy URL: {$proxyUrl}\n";
        
        $startTime = microtime(true);
        $response = Http::timeout(60)->get($proxyUrl);
        $duration = microtime(true) - $startTime;
        
        echo "[DEBUG] Response Status: " . $response->status() . "\n";
        echo "[DEBUG] Duration: " . round($duration, 2) . "s\n";
        
        $this->assertEquals(200, $response->status(), "Proxy {$proxy->name} failed with status " . $response->status());
        
        $html = $response->body();
        
        // Verifica la presenza di dati reali (non un captcha)
        // Ad esempio la tabella 'Standard Stats' o il nome del club 'Spezia' in un <h1>
        $hasSpezia = str_contains($html, 'Spezia');
        $hasStatsTable = str_contains($html, 'Standard Stats');
        
        echo "[DEBUG] Contains 'Spezia': " . ($hasSpezia ? 'YES' : 'NO') . "\n";
        echo "[DEBUG] Contains 'Standard Stats': " . ($hasStatsTable ? 'YES' : 'NO') . "\n";
        
        if (!$hasStatsTable) {
            // Salviamo il body per debugging se fallisce
            file_put_contents(storage_path('logs/debug_proxy_fail.html'), $html);
            echo "[DEBUG] HTML saved to storage/logs/debug_proxy_fail.html\n";
        }
        
        $this->assertTrue($hasSpezia, "HTML does not contain 'Spezia'. Possible block.");
        $this->assertTrue($hasStatsTable, "HTML does not contain 'Standard Stats' table. Likely a captcha/challenge page.");
        
        echo "[SUCCESS] ZenRows successfully bypassed Cloudflare and retrieved Spezia 2022 stats!\n";
    }
}
