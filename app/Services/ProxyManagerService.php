<?php

namespace App\Services;

use App\Models\ProxyService;
use App\Models\ImportLog;
use App\Services\ProxyProviders\ScraperApiProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class ProxyManagerService
{
    protected array $providers = [
        'ScraperAPI' => \App\Services\ProxyProviders\ScraperApiProvider::class,
        'ScrapingBee' => \App\Services\ProxyProviders\ScrapingBeeProvider::class,
        'ZenRows' => \App\Services\ProxyProviders\ZenRowsProvider::class,
    ];

    /**
     * Cache riga per riga per evitare switch infiniti nella stessa request
     */
    protected array $unreliableProxies = [];

    /**
     * Smart Rotation (v6.0): Get the best proxy based on remaining credits.
     * Logic:
     * 1. Active proxies only.
     * 2. Exclude temporarily unreliable proxies.
     * 3. Sort by (limit_monthly - current_usage) DESC (Most credits first).
     * 4. Tie-break: js_cost ASC (Cheapest JS first).
     */
    public function getBestProxy(): ?ProxyService
    {
        $proxies = ProxyService::where('is_active', true)
            ->get()
            ->filter(function ($proxy) {
                // Must have credits
                return $proxy->current_usage < $proxy->limit_monthly;
            })
            ->filter(function ($proxy) {
                // Not in unreliable list for this session
                return !in_array($proxy->slug, $this->unreliableProxies);
            })
            ->sort(function ($a, $b) {
                // Primary: priority ASC (1 = first choice, 2 = fallback, 3 = last resort)
                if ($a->priority !== $b->priority) {
                    return $a->priority <=> $b->priority;
                }

                // Secondary: remaining credits DESC (same priority → use the one with more credits)
                $remA = $a->limit_monthly - $a->current_usage;
                $remB = $b->limit_monthly - $b->current_usage;
                if ($remA !== $remB) {
                    return $remB <=> $remA;
                }

                // Tertiary: js_cost ASC
                return $a->js_cost <=> $b->js_cost;
            });

        $best = $proxies->first();

        if ($best) {
            $remaining = $best->limit_monthly - $best->current_usage;
            $this->audit("Smart Rotation: Selezionato '{$best->name}' (Saldo: {$remaining}, Costo JS: {$best->js_cost})", 'info', $best->slug);
        } else {
            $this->audit("ERRORE CRITICO: Nessun proxy affidabile con crediti trovato!", 'error');
        }

        return $best;
    }

    /**
     * Mark a proxy as unreliable for the remainder of this request.
     */
    public function markAsUnreliable(ProxyService $proxy, string $reason): void
    {
        if (!in_array($proxy->slug, $this->unreliableProxies)) {
            $this->unreliableProxies[] = $proxy->slug;
            $this->audit("Switching: '{$proxy->name}' marcato come inaffidabile. Motivo: {$reason}. Ricalcolo rotazione...", 'warning', $proxy->slug);
        }
    }

    /**
     * Get the first available active proxy based on priority (Legacy).
     * Now primarily used for background tasks where priority matters more than credit balance.
     */
    public function getActiveProxy(): ?ProxyService
    {
        return $this->getBestProxy();
    }

    /**
     * Genera l'URL del proxy usando il provider corretto.
     */
    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        $providerClass = $this->providers[$proxy->name] ?? ScraperApiProvider::class;
        return app($providerClass)->getProxyUrl($proxy, $targetUrl);
    }

    /**
     * Sincronizza tutti i bilanci.
     */
    public function syncBalances(): void
    {
        $proxies = ProxyService::where('is_active', true)->get();

        foreach ($proxies as $proxy) {
            try {
                $this->syncBalance($proxy);
                if ($proxies->count() > 1) {
                    sleep(6);
                }
            } catch (\Exception $e) {
                // Continue
            }
        }
    }

    /**
     * Sync balance for a specific proxy.
     */
    public function syncBalance(ProxyService $proxy): void
    {
        try {
            $providerClass = $this->providers[$proxy->name] ?? ScraperApiProvider::class;
            $provider = app($providerClass);
            
            $balance = $provider->checkBalance($proxy);
            
            $proxy->update([
                'current_usage' => $balance['used'],
                'limit_monthly' => $balance['limit'],
            ]);
            $proxy->touch(); // Forza updated_at anche se i valori sono identici

            $this->audit("Sincronizzazione '{$proxy->name}': {$balance['used']}/{$balance['limit']}", 'info', $proxy->slug);
            Log::info("[ProxySync] Sincronizzazione '{$proxy->name}' completata ({$balance['used']}/{$balance['limit']})");
        } catch (\Exception $e) {
            $this->audit("Errore sincronizzazione '{$proxy->name}': " . $e->getMessage(), 'error', $proxy->slug);
            throw $e;
        }
    }

    /**
     * Test connection for a specific proxy.
     */
    public function testConnection(ProxyService $proxy, ?string $testUrl = null): bool
    {
        try {
            $providerClass = $this->providers[$proxy->name] ?? ScraperApiProvider::class;
            $provider = app($providerClass);
            
            $testUrl = $testUrl ?: 'https://example.com';
            $proxyUrl = $provider->getProxyUrl($proxy, $testUrl);
            
            $response = \Illuminate\Support\Facades\Http::timeout(30)->get($proxyUrl);
            
            if ($response->successful()) {
                $this->audit("Test connessione '{$proxy->name}': OK", 'info', $proxy->slug);
                return true;
            }
            
            $this->audit("Test connessione '{$proxy->name}' FALLITO: " . $response->status(), 'warning', $proxy->slug);
            return false;
        } catch (\Exception $e) {
            $this->audit("Errore test connessione '{$proxy->name}': " . $e->getMessage(), 'error', $proxy->slug);
            return false;
        }
    }

    /**
     * Logging centralizzato e per-provider.
     */
    protected function audit(string $message, string $level = 'info', ?string $slug = null): void
    {
        $baseDir = storage_path('logs/Proxy_Services');
        if (!File::isDirectory($baseDir)) {
            File::makeDirectory($baseDir, 0755, true);
        }

        $timestamp = now()->toDateTimeString();
        $formattedMessage = "[{$timestamp}] [" . strtoupper($level) . "] " . ($slug ? "[{$slug}] " : "") . $message . PHP_EOL;
        
        File::append($baseDir . '/proxy_audit.log', $formattedMessage);
        
        if ($slug) {
            $providerFile = strtolower($slug) . '.log';
            File::append($baseDir . '/' . $providerFile, $formattedMessage);
        }
        
        Log::channel('single')->$level("[ProxyManager] " . $formattedMessage);

        // AGGIUNTA: Log anche nel database per visibilità in dashboard Filament
        try {
            ImportLog::create([
                'import_type'        => "Proxy" . ($slug ? ": " . strtoupper($slug) : ""),
                'status'             => strtoupper($level),
                'details'            => $message,
                'original_file_name' => 'ProxyManager',
            ]);
        } catch (\Exception $e) {
            // Ignoriamo silenziose se stiamo loggando e il DB ha problemi (evitiamo loop infiniti)
            Log::error("[ProxyManager DB LOG FAIL] " . $e->getMessage());
        }
    }
}
