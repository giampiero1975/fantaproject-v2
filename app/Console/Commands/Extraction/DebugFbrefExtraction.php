<?php

namespace App\Console\Commands\Extraction;

use Illuminate\Console\Command;
use App\Services\FbrefScrapingService;

class DebugFbrefExtraction extends Command
{
    protected $signature = 'fbref:test-file {fileName}';
    protected $description = 'Legge un file HTML e testa il parser';
    
    public function handle(FbrefScrapingService $service)
    {
        $fileName = $this->argument('fileName');
        $path = storage_path("app/debug_html/{$fileName}");
        
        try {
            $this->info("Leggo il file: $fileName");
            
            // Usiamo lo schema dei portieri per Falcone
            $data = $service->scrapeFromLocalFile($path, 'player_keeper_stats');
            
            // Sostituisci il blocco successivo con questo:
            if (isset($data['error'])) {
                $this->error("❌ ERRORE: " . $data['error']);
                return;
            }
            
            if (empty($data)) {
                $this->error("❌ IL PARSER NON HA TROVATO DATI (Array vuoto).");
            } else {
                $this->info("✅ SUCCESSO!");
                // Ora il count() non darà più errore perché controlliamo se è un array
                foreach($data as $key => $rows) {
                    $this->line("- Tabella $key: " . count($rows) . " righe trovate.");
                }
            }
        } catch (\Exception $e) {
            $this->error("Errore: " . $e->getMessage());
        }
    }
}