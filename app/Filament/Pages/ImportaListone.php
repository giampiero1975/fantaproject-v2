<?php

namespace App\Filament\Pages;

use App\Imports\MainRosterImport;
use App\Models\ImportLog;
use App\Models\Player;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportaListone extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = '4. Importa Listone';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $title           = 'Importa Listone (Quotazioni)';
    protected static string  $view            = 'filament.pages.importa-listone';

    /** Proprietà Livewire per il file caricato */
    public ?string $rosterFile = null;

    protected function getHeaderActions(): array
    {
        return [
            // ── Importa Listone ─────────────────────────────────────────────
            Action::make('importa_listone')
                ->label('Carica ed Importa')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Importa Listone Quotazioni')
                ->modalDescription('Carica il file Excel ufficiale (.xlsx) scaricato da Fantagazzetta. Il processo eseguirà un soft-delete dei giocatori esistenti e importerà i nuovi dati.')
                ->form([
                    FileUpload::make('roster_file')
                        ->label('File Listone (.xlsx)')
                        ->disk('livewire')       // disco dedicato (root: storage/app)
                        ->directory('imports')   // → storage/app/imports/<filename>.xlsx
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // FileUpload restituisce il path relativo al disco configurato
                    // es. "imports/01KMJH03VKB239D3TFN9XH8XZ8.xlsx"
                    $paths   = $data['roster_file'] ?? [];
                    $relPath = is_array($paths) ? (array_values($paths)[0] ?? null) : $paths;

                    if (!$relPath) {
                        Notification::make()->title('Nessun file caricato')->warning()->send();
                        return;
                    }

                    $this->runImport($relPath);
                }),
        ];
    }

    private function runImport(string $relPath): void
    {
        $logDir  = storage_path('logs/Roster');
        $logPath = $logDir . '/RosterImport.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);

        $originalFileName = basename($relPath);

        // ── Risoluzione disco + percorso ───────────────────────────────────────
        // Excel::import($importer, $file, $disk) — cerca il file relativo al disco.
        // Proviamo tutti i dischi in ordine di probabilità, con e senza il prefisso
        // della subdirectory, per coprire ogni scenario di deploy.
        $diskName = null;
        $fullPath = null;

        $searches = [
            ['livewire', $relPath],
            ['livewire', basename($relPath)],
            ['local',    $relPath],
            ['local',    basename($relPath)],
            ['public',   $relPath],
            ['public',   basename($relPath)],
        ];

        foreach ($searches as [$disk, $path]) {
            if (Storage::disk($disk)->exists($path)) {
                $diskName = $disk;
                $relPath  = $path;
                $fullPath = Storage::disk($disk)->path($path);
                break;
            }
        }

        if (!$fullPath || !file_exists($fullPath)) {
            $logger->error('❌  File non trovato. Path cercato: ' . $relPath);
            $logger->error('   livewire root: ' . Storage::disk('livewire')->path(''));
            $logger->error('   local root   : ' . Storage::disk('local')->path(''));
            $logger->error('   public root  : ' . Storage::disk('public')->path(''));
            throw new \RuntimeException(
                "File non trovato in nessuno storage disk. " .
                "Path cercato: [{$relPath}]. Controlla storage/logs/Roster/RosterImport.log."
            );
        }

        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info('📥  AVVIO IMPORTAZIONE LISTONE');
        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info("📄  File    : {$originalFileName}");
        $logger->info("💾  Disco   : {$diskName}");
        $logger->info("📂  RelPath : {$relPath}");
        $logger->info("📂  FullPath: {$fullPath}");
        $logger->info("🕐  Ora     : " . now()->format('Y-m-d H:i:s'));

        $importLog = ImportLog::create([
            'original_file_name' => $originalFileName,
            'import_type'        => 'roster_quotazioni',
            'status'             => 'in_corso',
            'details'            => 'Avvio importazione Listone via Filament.',
        ]);
        $logger->info("📋  ImportLog ID: {$importLog->id} creato (status: in_corso)");

        $mainImporter = new MainRosterImport();

        try {
            $logger->info('🗑️  Soft-delete giocatori esistenti...');
            Player::query()->delete();
            $logger->info('✔️  Soft-delete completato.');

            // Passa il path relativo + nome disco a maatwebsite/excel
            Excel::import($mainImporter, $relPath, $diskName);

            $tuttiImporter = $mainImporter->getTuttiSheetImporter();

            $processed = $tuttiImporter ? $tuttiImporter->getProcessedCount() : 0;
            $created   = $tuttiImporter ? $tuttiImporter->getCreatedCount()   : 0;
            $updated   = $tuttiImporter ? $tuttiImporter->getUpdatedCount()   : 0;

            $importLog->status         = 'successo';
            $importLog->details        = "Importazione completata. Processati: {$processed}, Creati: {$created}, Aggiornati: {$updated}.";
            $importLog->rows_processed = $processed;
            $importLog->rows_created   = $created;
            $importLog->rows_updated   = $updated;
            $importLog->save();

            $logger->info("✅  COMPLETATO — Processati: {$processed} | Creati: {$created} | Aggiornati: {$updated}");
            $logger->info("📋  ImportLog ID: {$importLog->id} aggiornato (status: successo)");
            $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Notification::make()
                ->title('Importazione completata!')
                ->body("Giocatori processati: {$processed} | Creati: {$created} | Aggiornati: {$updated}")
                ->success()
                ->send();

        } catch (Throwable $e) {
            $importLog->status  = 'fallito';
            $importLog->details = 'Errore: ' . $e->getMessage();
            $importLog->save();

            $logger->error("❌  ERRORE: " . $e->getMessage());
            $logger->error("   Stack: " . substr($e->getTraceAsString(), 0, 800));
            $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Notification::make()
                ->title('Errore importazione')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
