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

        // 1. Carica parametri di default dal DB (se presenti)
        if (!empty($proxy->default_params)) {
            $params = array_merge($params, $proxy->default_params);
        }

        // 2. Logica Specifica FBref (JS Rendering & Premium)
        // Per ora gestiti dinamicamente per non bruciare crediti inutilmente
        if (str_contains($targetUrl, 'fbref.com')) {
            // Se la URL contiene parametri di rendering specifici o è una pagina nota per JS
            // aggiungiamo render=true solo se necessario.
            // Per ora lo lasciamo spento (Standard API Mode) come da "Gold Standard".
        }

        return $proxy->base_url . '?' . http_build_query($params);
    }
}
