<?php

namespace App\Traits;

use Symfony\Component\DomCrawler\Crawler;

trait ParsesFbrefHtml
{
    public function parseEntirePage(string $html): array
    {
        $uncommentedHtml = preg_replace('//s', '$1', $html);
        $crawler = new Crawler($uncommentedHtml);
        $finalMap = [];
        
        $crawler->filter('table')->each(function (Crawler $table) use (&$finalMap) {
            $tableId = $table->attr('id') ?? 'table_' . count($finalMap);
            $finalMap[$tableId] = [];
            
            $table->filter('tbody tr')->each(function (Crawler $row) use (&$finalMap, $tableId) {
                // Estrazione del nome visualizzato su FBref (squad o team)
                $squadNode = $row->filter('[data-stat="squad"], [data-stat="team"]');
                $fbrefName = $squadNode->count() > 0 ? trim($squadNode->text()) : null;
                
                if (!$fbrefName) return;
                
                $rowData = [];
                $row->filter('td, th')->each(function (Crawler $cell) use (&$rowData) {
                    if ($statName = $cell->attr('data-stat')) {
                        $rowData[$statName] = trim($cell->text());
                        
                        // Estrazione ID FBref (es. ffcbe334)
                        $link = $cell->filter('a')->first();
                        if ($link->count() > 0 && preg_match('/squads\/([^\/]+)/', $link->attr('href'), $m)) {
                            $rowData['fbref_id'] = $m[1];
                        }
                    }
                });
                    
                    $finalMap[$tableId][$fbrefName] = $rowData;
            });
        });
            
            return $finalMap;
    }
    
    private function getRowKey(Crawler $row, int $index): string
    {
        // FBref usa 'squad' come data-stat per il nome della squadra nelle classifiche
        // Lo cerchiamo sia in 'td' che in 'th' perché a volte è l'header della riga
        $squadNode = $row->filter('[data-stat="squad"]');
        
        if ($squadNode->count() > 0) {
            return trim($squadNode->text());
        }
        
        // Backup: se non c'è squad, cerchiamo 'team'
        $teamNode = $row->filter('[data-stat="team"]');
        if ($teamNode->count() > 0) {
            return trim($teamNode->text());
        }
        
        return 'row_' . $index;
    }
}