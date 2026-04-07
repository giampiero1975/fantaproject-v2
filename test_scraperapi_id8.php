<?php

echo "==========================================" . PHP_EOL;
echo "  TEST ScraperAPI ID=8 — Base Pool (No Premium)" . PHP_EOL;
echo "  " . now()->toDateTimeString() . PHP_EOL;
echo "==========================================" . PHP_EOL . PHP_EOL;

$proxy    = \App\Models\ProxyService::findOrFail(8);
$provider = app(\App\Services\ProxyProviders\ScraperApiProvider::class);

// --- Costruzione URL ---
$targetUrl = 'https://fbref.com/en/comps/20/Serie-A-Stats';
$proxyUrl  = $provider->getProxyUrl($proxy, $targetUrl);

echo "[INFO] Parametri URL generato dal Provider in Laravel:" . PHP_EOL;
parse_str(parse_url($proxyUrl, PHP_URL_QUERY), $params);
foreach ($params as $k => $v) {
    if ($k === 'api_key') $v = substr($v, 0, 12) . '...';
    echo "  {$k} = {$v}" . PHP_EOL;
}
echo PHP_EOL;

// --- Richiesta ---
echo "[INFO] Invio richiesta (timeout 120s)..." . PHP_EOL;
$start = microtime(true);
try {
    // Usiamo il facade HTTP esatto come nel trait ManagesFbrefScraping
    $response = \Illuminate\Support\Facades\Http::timeout(120)->withoutVerifying()->get($proxyUrl);
    $elapsed  = round(microtime(true) - $start, 2);
    $status   = $response->status();
    $body     = $response->body();

    echo "[RESULT] Status : {$status}" . PHP_EOL;
    echo "[RESULT] Tempo  : {$elapsed}s" . PHP_EOL . PHP_EOL;

    if ($response->successful()) {
        $hasFbref  = str_contains($body, 'fbref');
        $hasSerieA = str_contains($body, 'Serie A') || str_contains($body, 'serie-a');
        $hasTable  = str_contains($body, 'stats_table') || str_contains($body, 'table_container');
        echo "[RESULT] ✅ SUCCESSO!" . PHP_EOL;
        echo "  Contiene 'fbref'       : " . ($hasFbref  ? "SI ✅" : "NO ⚠️") . PHP_EOL;
        echo "  Contiene 'Serie A'     : " . ($hasSerieA ? "SI ✅" : "NO ⚠️") . PHP_EOL;
        echo "  Contiene tabelle stats : " . ($hasTable  ? "SI ✅" : "NO ⚠️") . PHP_EOL;
        echo "  Dimensione body        : " . number_format(strlen($body)) . " bytes" . PHP_EOL;
        echo PHP_EOL . "[BODY_SNIPPET]: " . substr($body, 0, 100) . "..." . PHP_EOL;
    } else {
        echo "[RESULT] ❌ FALLITO. HTTP {$status}" . PHP_EOL;
        echo "[BODY]: " . substr($body, 0, 400) . PHP_EOL;
    }
} catch (\Exception $e) {
    $elapsed = round(microtime(true) - $start, 2);
    echo "[RESULT] ❌ ECCEZIONE dopo {$elapsed}s: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "==========================================" . PHP_EOL;
echo "  TEST COMPLETATO" . PHP_EOL;
echo "==========================================" . PHP_EOL;
