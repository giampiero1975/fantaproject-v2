<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Illuminate\Support\Facades\Log;

class MainRosterImport implements WithMultipleSheets, SkipsUnknownSheets
{
    private TuttiSheetImport $tuttiSheetImporter;

    public function __construct()
    {
        $this->tuttiSheetImporter = new TuttiSheetImport();
    }

    public function sheets(): array
    {
        return [
            'Tutti' => $this->tuttiSheetImporter,
        ];
    }

    public function getTuttiSheetImporter(): TuttiSheetImport
    {
        return $this->tuttiSheetImporter;
    }

    public function onUnknownSheet($sheetName): void
    {
        Log::info("MainRosterImport: Foglio sconosciuto '{$sheetName}' ignorato.");
    }
}
