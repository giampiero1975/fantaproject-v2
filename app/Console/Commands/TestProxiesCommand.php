<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProxyService;
use App\Services\ProxyManagerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class TestProxiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxies:test-all {--url=https://fbref.com/en/comps/11/Serie-A-Stats}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all active proxies against a target URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetUrl = $this->option('url');
        $this->info("🔍 Avvio Test Intensivo Proxy per URL: $targetUrl");
        
        $proxies = ProxyService::where('is_active', true)->get();
        if ($proxies->isEmpty()) {
            $this->error("❌ Nessun proxy attivo trovato.");
            return 1;
        }

        $results = [];
        $proxyManager = app(ProxyManagerService::class);

        foreach ($proxies as $proxy) {
            $this->info("--- Testing [{$proxy->name}] ---");
            
            try {
                $proxyUrl = $proxyManager->getProxyUrl($proxy, $targetUrl);
                
                $startTime = microtime(true);
                $response = Http::timeout(60)->get($proxyUrl);
                $duration = round(microtime(true) - $startTime, 2);

                $status = $response->status();
                $success = $status === 200;
                $size = strlen($response->body());
                
                // Salviamo un dump della risposta per diagnostica (SIA SUCCESSO CHE FALLIMENTO)
                $fileName = "test_" . $proxy->slug . ($success ? "" : "_error_" . $status) . ".html";
                File::put(storage_path("logs/Proxy_Services/$fileName"), $response->body());

                // Sincronizziamo il bilancio dopo il test
                try {
                    $proxyManager->syncBalance($proxy);
                } catch (\Exception $e) {
                    $this->warn("   ⚠️ Impossibile aggiornare saldo per {$proxy->name}");
                }

                $results[] = [
                    'Provider' => $proxy->name,
                    'Status' => $success ? '✅ OK' : '❌ FAIL',
                    'HTTP' => $status,
                    'Time' => "{$duration}s",
                    'Size' => number_format($size / 1024, 2) . ' KB',
                    'Credits' => $proxy->fresh()->current_usage . '/' . $proxy->limit_monthly
                ];

                if ($success) {
                    $this->info("   ✅ Successo in {$duration}s ($size bytes)");
                } else {
                    $this->error("   ❌ Fallito con status $status");
                }

            } catch (\Exception $e) {
                $this->error("   🔥 Errore: " . $e->getMessage());
                $results[] = [
                    'Provider' => $proxy->name,
                    'Status' => '🔥 ERROR',
                    'HTTP' => 'N/A',
                    'Time' => 'N/A',
                    'Size' => 'N/A',
                    'Credits' => $proxy->current_usage
                ];
            }
        }

        $this->newLine();
        $this->table(['Provider', 'Status', 'HTTP', 'Time', 'Size', 'Credits'], $results);
        
        $this->info("💾 Dump HTML salvati in storage/logs/Proxy_Services/test_*.html");

        return 0;
    }
}
