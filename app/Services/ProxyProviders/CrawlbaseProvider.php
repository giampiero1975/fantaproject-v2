<?php

namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CrawlbaseProvider implements ProxyProviderInterface
{
    /**
     * Get the formatted proxy URL for a target URL.
     */
    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        $params = [
            'token' => trim($proxy->api_key),
            'url' => $targetUrl,
        ];

        // Always use javascript=true if needed or based on standard
        // But the user requested: "Always append &javascript=true when render is requested"
        // Since our system currently doesn't pass a 'render' flag yet, we'll implement it
        // but for now, we follow the user's specific request.
        $params['javascript'] = 'true';

        return 'https://api.crawlbase.com/?' . http_build_query($params);
    }

    /**
     * Check account balance and return used/limit.
     */
    public function checkBalance(ProxyService $proxy): array
    {
        $token = trim($proxy->api_key);
        // Use product=crawling-api as it is the most common for JS tokens
        $response = Http::timeout(15)->get("https://api.crawlbase.com/account", [
            'token' => $token,
            'product' => 'crawling-api'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Try to map remaining_requests first
            $remaining = $data['remaining_requests'] ?? null;
            $limit = $data['request_limit'] ?? null;

            // Fallback to currentMonth stats if specific keys are missing
            if ($remaining === null && isset($data['currentMonth'])) {
                $used = (int) ($data['currentMonth']['totalSuccess'] ?? 0);
                $limit = $proxy->limit_monthly ?: 1000;
                $remaining = max(0, $limit - $used);
            } else {
                $limit = $limit ?? ($proxy->limit_monthly ?: 1000);
                $remaining = $remaining ?? $limit;
                $used = $limit - $remaining;
            }

            return [
                'used' => (int) $used,
                'limit' => (int) $limit,
                'remaining' => (int) $remaining,
            ];
        }

        if ($response->status() === 429) {
            $this->audit($proxy, "Rate Limit (429) rilevato. Restituzione dati attuali dal DB per evitare blocchi.", 'warning');
            return [
                'used' => (int) $proxy->current_usage,
                'limit' => (int) $proxy->limit_monthly,
                'remaining' => (int) max(0, $proxy->limit_monthly - $proxy->current_usage),
            ];
        }

        $this->logError($proxy, $response);
        throw new \Exception("Errore Crawlbase Balance Check: " . $response->status());
    }

    protected function audit(ProxyService $proxy, string $message, string $level = 'info'): void
    {
        $timestamp = now()->toDateTimeString();
        $formattedMessage = "[{$timestamp}] [" . strtoupper($level) . "] [Crawlbase] {$message}" . PHP_EOL;
        \Illuminate\Support\Facades\File::append(storage_path('logs/Proxy_Services/crawlbase.log'), $formattedMessage);
    }

    /**
     * Smart logging for specific Crawlbase errors.
     */
    protected function logError(ProxyService $proxy, $response): void
    {
        $status = $response->status();
        $body = $response->body();
        $timestamp = now()->toDateTimeString();
        
        $message = "ERRORE Crawlbase [{$status}]: {$body}";
        
        if ($status === 403) {
            $message = "ERRORE Crawlbase [403]: Token Invalido o Accesso Negato.";
        } elseif ($status === 429) {
            $message = "ERRORE Crawlbase [429]: Quota Superata o Rate Limit.";
        }

        $formattedMessage = "[{$timestamp}] [ERROR] [{$proxy->name}] {$message}" . PHP_EOL;
        
        $logFile = storage_path('logs/Proxy_Services/crawlbase.log');
        File::append($logFile, $formattedMessage);
        
        // Also log to general audit
        File::append(storage_path('logs/Proxy_Services/audit.log'), $formattedMessage);
    }
}
