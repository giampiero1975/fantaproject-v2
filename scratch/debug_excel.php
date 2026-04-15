<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class DebugImport implements ToCollection {
    public function collection(Collection $rows) {
        $first = $rows->first();
        if ($first) {
            echo "--- FIRST ROW ---\n";
            print_r($first->toArray());
            echo "--- END ---\n";
        }
    }
}

$file = 'storage/app/imports/01KP8H9QGYR2Q0SG8M4KS56NK1.xlsx';
if (!file_exists($file)) {
    die("File not found: $file\n");
}

Excel::import(new DebugImport, $file);
