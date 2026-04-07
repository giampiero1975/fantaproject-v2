<?php

namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;

class ScraperApiProvider implements ProxyProviderInterface
{
    public function checkBalance(ProxyService $proxy): array
    {
        $response = Http::timeout(10)->get('http://api.scraperapi.com/account', [
            'api_key' => $proxy->api_key
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Mapping: requestCount -> current_usage, requestLimit -> limit_monthly
            $used = $data['requestCount'] ?? 0;
            $limit = $data['requestLimit'] ?? 0;
            
            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit - $used
            ];
        }

        throw new \Exception("Impossibile recuperare il bilancio da ScraperAPI: " . $response->body());
    }

    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        $apiKey = $proxy->api_key ?: config('services.scraperapi.api_key');

        $params = [
            'api_key' => $apiKey,
            'url' => $targetUrl,
        ];

        // FBref ora richiede premium=true per le pagine storiche /comps/
        if (str_contains($targetUrl, 'fbref.com')) {
            $params['premium'] = 'true';
            $params['render'] = 'true';
        }

        return $proxy->base_url . '?' . http_build_query($params);
    }
}
