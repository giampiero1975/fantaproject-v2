<?php

namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScrapingAiProvider implements ProxyProviderInterface
{
    /**
     * Get the remaining credits for WebScraping.ai.
     * Endpoint: https://api.webscraping.ai/account?api_key={key}
     */
    public function checkBalance(ProxyService $proxy): array
    {
        $response = Http::timeout(10)->get('https://api.webscraping.ai/account', [
            'api_key' => $proxy->api_key
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Mapping based on ACTUAL diagnostic: {"email":"...","remaining_api_calls":2000,...}
            $remaining = $data['remaining_api_calls'] ?? 0;
            $limit = 2000; // Default per il piano free, purtroppo non passato nel JSON flat
            $used = $limit - $remaining;
            
            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
            ];
        }

        throw new \Exception("Impossibile recuperare il bilancio da WebScraping.ai: " . $response->body());
    }

    /**
     * Build the proxy URL for scraping.
     * Endpoint: https://api.webscraping.ai/html?api_key={key}&url={url}
     */
    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        // Ritorna l'URL esattamente come richiesto dal "Capo":
        // https://api.webscraping.ai/html?api_key={key}&url={url}
        return 'https://api.webscraping.ai/html?' . http_build_query([
            'api_key' => trim($proxy->api_key),
            'url' => $targetUrl, // http_build_query esegue già urlencode
        ]);
    }
}
