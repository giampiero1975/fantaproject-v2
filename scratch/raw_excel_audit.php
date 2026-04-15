<?php

require __DIR__ . '/../vendor/autoload.php';

$file = 'storage/app/private/imports/01KP95F8QM5XJJ352V1ZV4HBQD.xlsx';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

echo "Attempting to load: $file\n";

try {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $sheetNames = $spreadsheet->getSheetNames();
    echo "SHEETS: " . implode(', ', $sheetNames) . "\n";

    foreach ($sheetNames as $name) {
        $sheet = $spreadsheet->getSheetByName($name);
        $highestRow = min($sheet->getHighestRow(), 100); // Check first 100 rows
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        echo "\nSheet [$name] (Rows: $highestRow, Cols: $highestColumnIndex)\n";

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];
            
            // Search for Maignan in any column
            $found = false;
            foreach ($rowData as $cell) {
                if (stripos((string)$cell, 'Maignan') !== false) {
                    $found = true;
                    break;
                }
            }

            if ($found || $row === 1) { // Print header or found row
                echo "Row $row: " . implode(' | ', array_map(fn($v) => (string)$v, $rowData)) . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
