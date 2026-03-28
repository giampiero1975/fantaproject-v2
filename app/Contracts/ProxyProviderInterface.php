<?php

namespace App\Contracts;

use App\Models\ProxyService;

interface ProxyProviderInterface
{
    /**
     * Get the current balance/usage from the provider.
     * Returns an array with ['used', 'limit', 'remaining']
     */
    public function checkBalance(ProxyService $proxy): array;

    /**
     * Build the dynamic URL for the request.
     */
    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string;
}
