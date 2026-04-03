<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Models\ProxyService;
use App\Services\ProxyManagerService;

class ZenRowsConnectionTest extends TestCase
{
    /**
     * Test della connessione ZenRows in modalita API URL (Safe Mode)
     */
    public function test_zenrows_api_connection()
    {
        $proxy = ProxyService::where('name', 'LIKE', '%ZenRows%')->first();
        
        if (!$proxy) {
            $this->markTestSkipped('Proxy ZenRows non trovato nel DB.');
        }

        $url = 'https://fbref.com/en/comps/11/2024-2025/2024-2025-Serie-A-Stats';
        $proxyManager = app(ProxyManagerService::class);
        $proxyUrl = $proxyManager->getProxyUrl($proxy, $url);

        echo "\n🔍 TESTING ZENROWS URL: " . $proxyUrl . "\n";

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->get($proxyUrl);

            echo "✅ STATUS CODE: " . $response->status() . "\n";
            echo "📄 BODY SNIPPET: " . substr($response->body(), 0, 200) . "...\n";

            $this->assertTrue($response->successful(), "La richiesta a ZenRows è fallita con status: " . $response->status());
        } catch (\Exception $e) {
            echo "❌ EXCEPTION CAUGHT: " . $e->getMessage() . "\n";
            echo "📌 TRACE: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $this->fail("Eccezione durante la connessione: " . $e->getMessage());
        }
    }
}
