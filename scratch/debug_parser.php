<?php

require_once 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

$html = file_get_contents('tests/fixtures/venezia_21_22.html');
$crawler = new Crawler($html);

echo "--- Tabelle trovate con .stats_table ---\n";
$crawler->filter('table.stats_table')->each(function ($node) {
    echo "ID: " . $node->attr('id') . " | Class: " . $node->attr('class') . "\n";
});

echo "\n--- Cerco specificamente stats_standard_11 ---\n";
$node = $crawler->filter('#stats_standard_11');
if ($node->count() > 0) {
    echo "Trovata! Classi: " . $node->attr('class') . "\n";
} else {
    echo "NON TROVATA!\n";
}
