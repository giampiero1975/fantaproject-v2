<?php

namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZenRowsProvider implements ProxyProviderInterface
{
    protected string $baseUrl = 'https://api.zenrows.com/v1';

    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        $params = [
            'apikey' => $proxy->api_key,
            'url' => $targetUrl,
        ];

        // ZenRows usa js_render=true per il rendering JS
        if ($proxy->js_cost > 1) {
            $params['js_render'] = 'true';
        }

        return $this->baseUrl . '?' . http_build_query($params);
    }

    public function checkBalance(ProxyService $proxy): array
    {
        try {
            // ZenRows richiede la API Key negli header per questo endpoint
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-API-Key' => $proxy->api_key,
                ])
                ->get("{$this->baseUrl}/subscriptions/self/details");

            if ($response->successful()) {
                $data = $response->json();
                
                $used = $data['usage'] ?? 0;
                $limit = $proxy->limit_monthly ?: 1000; // ZenRows trial è solitamente 1000
                $remaining = max(0, $limit - $used);

                $this->audit($proxy, "Sincronizzazione: {$used}/{$limit}", 'info');

                return [
                    'used' => (int) $used,
                    'limit' => (int) $limit,
                    'remaining' => (int) $remaining,
                ];
            }

            $this->logError($proxy, $response);
            throw new \Exception("Errore ZenRows Balance: " . $response->status());

        } catch (\Exception $e) {
            $this->audit($proxy, "Errore Sincronizzazione: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    protected function audit(ProxyService $proxy, string $message, string $level = 'info'): void
    {
        $timestamp = now()->toDateTimeString();
        $formattedMessage = "[{$timestamp}] [" . strtoupper($level) . "] [ZenRows] {$message}" . PHP_EOL;
        \Illuminate\Support\Facades\File::append(storage_path('logs/Proxy_Services/zenrows.log'), $formattedMessage);
    }

    protected function logError(ProxyService $proxy, $response): void
    {
        $this->audit($proxy, "ERRORE [{$response->status()}]: " . $response->body(), 'error');
    }
}
