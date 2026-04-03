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
        // Correzione 422: Aggiungiamo js_render=true obbligatorio per bypassare Cloudflare.
        
        $params = [
            'apikey' => $proxy->api_key,
            'url' => $targetUrl,
            'premium_proxy' => 'true',
            'proxy_country' => 'us',
            'js_render' => 'true',
        ];

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
                
                // ZenRows usa 'credits_used' e 'credits_total' nella risposta v1
                $used = $data['credits_used'] ?? 0;
                $limit = $data['credits_total'] ?? ($proxy->limit_monthly ?: 1000);
                $remaining = $data['credits_remaining'] ?? max(0, $limit - $used);

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
