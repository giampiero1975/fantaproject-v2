<?php

namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapingBeeProvider implements ProxyProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://app.scrapingbee.com/api/v1';

    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        $apiKey = $proxy->api_key ?: config('services.scrapingbee.api_key');
        
        $params = [
            'api_key' => $apiKey,
            'url' => $targetUrl,
        ];

        // Se il proxy ha una preferenza per il rendering nel DB o se usiamo parametri di sistema
        // In ScrapingBee, il rendering è controllato dal parametro 'render_js'
        if ($proxy->js_cost > 1) {
            $params['render_js'] = 'true';
        }

        return $this->baseUrl . '?' . http_build_query($params);
    }

    public function checkBalance(ProxyService $proxy): array
    {
        $apiKey = $proxy->api_key ?: config('services.scrapingbee.api_key');

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/usage", [
                'api_key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $used = $data['used_api_credit'] ?? 0;
                $limit = $data['max_api_credit'] ?? ($proxy->limit_monthly ?: 1000);
                $remaining = max(0, $limit - $used);

                $this->audit($proxy, "Sincronizzazione: {$used}/{$limit}", 'info');

                return [
                    'used' => (int) $used,
                    'limit' => (int) $limit,
                    'remaining' => (int) $remaining,
                ];
            }

            $this->logError($proxy, $response);
            throw new \Exception("Errore ScrapingBee Balance: " . $response->status());

        } catch (\Exception $e) {
            $this->audit($proxy, "Errore Sincronizzazione: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    protected function audit(ProxyService $proxy, string $message, string $level = 'info'): void
    {
        $timestamp = now()->toDateTimeString();
        $formattedMessage = "[{$timestamp}] [" . strtoupper($level) . "] [ScrapingBee] {$message}" . PHP_EOL;
        \Illuminate\Support\Facades\File::append(storage_path('logs/Proxy_Services/scrapingbee.log'), $formattedMessage);
    }

    protected function logError(ProxyService $proxy, $response): void
    {
        $this->audit($proxy, "ERRORE [{$response->status()}]: " . $response->body(), 'error');
    }
}
