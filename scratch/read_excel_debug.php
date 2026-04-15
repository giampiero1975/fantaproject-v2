<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__ . '/../storage/app/private/imports/01KP95F8QM5XJJ352V1ZV4HBQD.xlsx';
$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getSheetByName('Tutti');
$rows = $sheet->toArray(null, true, true, true);

echo "--- EXCEL AUDIT (First 20 Data Rows) ---\n";
$count = 0;
foreach ($rows as $index => $row) {
    if ($index < 3) continue; // Skip headers
    echo "Row $index | ID: {$row['A']} | Role: {$row['B']} | Name: {$row['D']} | Team: {$row['E']}\n";
    $count++;
    if ($count > 20) break;
}
