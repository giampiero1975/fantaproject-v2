<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$logs = App\Models\ImportLog::where('import_type', 'fbref_surgical_sync')
    ->latest()->get(['id','original_file_name','status','rows_updated','created_at']);

foreach ($logs as $l) {
    echo $l->id . ' | ' . $l->original_file_name . ' | ' . $l->status . ' | Aggiornati: ' . $l->rows_updated . ' | ' . $l->created_at . "\n";
}
