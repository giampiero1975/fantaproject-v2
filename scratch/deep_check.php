<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use Maatwebsite\Excel\Facades\Excel;

$file = 'storage/app/private/imports/01KP95F8QM5XJJ352V1ZV4HBQD.xlsx';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

$reader = \Maatwebsite\Excel\Facades\Excel::toCollection(new class implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
    public function sheets(): array { return []; }
}, $file);

// Maatwebsite doesn't expose sheet names easily via toCollection with empty sheets.
// Let's use PhpSpreadsheet directly.
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
$sheetNames = $spreadsheet->getSheetNames();

echo "SHEETS: " . implode(', ', $sheetNames) . "\n";

foreach ($sheetNames as $name) {
    echo "Processing sheet: $name\n";
    $sheet = $spreadsheet->getSheetByName($name);
    $highestRow = $sheet->getHighestRow();
    for ($row = 1; $row <= 10; $row++) { // Check first 10 rows for Maignan
        $val = $sheet->getCell('C' . $row)->getValue();
        if (stripos((string)$val, 'Maignan') !== false) {
            echo "FOUND MAIGNAN in sheet $name at row $row!\n";
            echo "ID (Col A): " . $sheet->getCell('A' . $row)->getValue() . "\n";
            echo "DATA (Full row): " . implode(' | ', $sheet->rangeToArray('A' . $row . ':R' . $row)[0]) . "\n";
        }
    }
}
