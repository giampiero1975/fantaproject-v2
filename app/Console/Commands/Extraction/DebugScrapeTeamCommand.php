<?php

namespace App\Console\Commands\Extraction;

use App\Traits\ManagesFbrefScraping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class DebugScrapeTeamCommand extends Command
{
    use ManagesFbrefScraping; // Fondamentale per ScraperAPI
    
    protected $signature = 'debug:scrape-team {url} {--save-html= : Nome del file in debug_html}';
    protected $description = 'Scarica l\'HTML di un URL FBref tramite Proxy';
    
    public function handle()
    {
        $url = $this->argument('url');
        $htmlFile = $this->option('save-html');
        
        $this->info("▶️ Avvio scaricamento via Proxy: {$url}");
        
        try {
            // Usa il metodo del Trait ManagesFbrefScraping
            $crawler = $this->fetchPageWithProxy($url);
            $rawHtml = $crawler->html();
            
            if ($htmlFile) {
                $path = storage_path("app/debug_html/{$htmlFile}");
                File::ensureDirectoryExists(dirname($path));
                File::put($path, $rawHtml);
                $this->info("✅ HTML salvato correttamente in: {$path}");
            } else {
                $this->warn("⚠️ HTML scaricato ma non salvato (usa --save-html)");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Errore durante lo scraping: " . $e->getMessage());
            return 1;
        }
    }
}