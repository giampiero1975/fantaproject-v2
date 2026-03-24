<?php

namespace App\Console\Commands\Extraction;

use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\File;

class DebugFbrefExtractionHtml extends Command
{
    /**
     * Il nome e la firma del comando.
     * @var string
     */
    protected $signature = 'debug:fbref-html {fileName}';
    
    /**
     * La descrizione del comando.
     * @var string
     */
    protected $description = 'Estrae dati dall\'HTML locale di un portiere (Falcone) e genera un JSON completo in storage';
    
    /**
     * Esegui il comando.
     */
    public function handle()
    {
        $fileName = $this->argument('fileName');
        $inputPath = storage_path("app/debug_html/{$fileName}");
        
        if (!File::exists($inputPath)) {
            $this->error("❌ File HTML non trovato.");
            return;
        }
        
        $html = File::get($inputPath);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $this->info("--- 🌪️ MODALITÀ ESTRATTORE UNIVERSALE (SINGOLO + SQUADRA) ---");
        
        $finalMap = [];
        
        $crawler->filter('table[id^="stats_"]')->each(function ($table) use (&$finalMap) {
            $tableId = $table->attr('id');
            $finalMap[$tableId] = [];
            
            $table->filter('tbody tr')->each(function ($row) use (&$finalMap, $tableId) {
                // Se c'è la colonna 'player', usiamo il nome come chiave, altrimenti usiamo 'year_id'
                $playerNode = $row->filter('td[data-stat="player"], th[data-stat="player"]');
                $yearNode = $row->filter('th[data-stat="year_id"]');
                
                $rowKey = $playerNode->count() ? trim($playerNode->text()) : ($yearNode->count() ? trim($yearNode->text()) : 'row_' . count($finalMap[$tableId]));
                
                $rowData = [];
                $row->filter('td')->each(function ($cell) use (&$rowData) {
                    if ($statName = $cell->attr('data-stat')) {
                        $rowData[$statName] = trim($cell->text());
                    }
                });
                    
                    if (!empty($rowData)) {
                        $finalMap[$tableId][$rowKey] = $rowData;
                    }
            });
                $this->comment("✅ Tabella {$tableId}: mappate " . count($finalMap[$tableId]) . " righe.");
        });
            
            $jsonOutput = json_encode($finalMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $outputPath = storage_path("app/debug_json/" . str_replace('.html', '.json', $fileName));
            File::ensureDirectoryExists(dirname($outputPath));
            File::put($outputPath, $jsonOutput);
            $this->info("📂 JSON salvato: " . basename($outputPath));
    }
}