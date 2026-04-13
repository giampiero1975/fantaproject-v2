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
    protected static ?string $navigationLabel = '5. Importa Listone';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $title           = 'Importa Listone (Quotazioni)';
    protected static string  $view            = 'filament.pages.importa-listone';

    /** Proprietà Livewire per il file caricato */
    public ?string $rosterFile = null;

    protected function getViewData(): array
    {
        return [
            'seasonsStatus' => \App\Models\Season::orderBy('season_year', 'desc')
                ->get()
                ->map(function ($season) {
                    $count = \App\Models\PlayerSeasonRoster::where('season_id', $season->id)->count();
                    
                    // Recupero ultimo log di successo per questa stagione
                    $lastLog = \App\Models\ImportLog::where('season_id', $season->id)
                        ->where('status', 'successo')
                        ->latest()
                        ->first();

                    return [
                        'id' => $season->id,
                        'name' => \App\Helpers\SeasonHelper::formatYear($season->season_year),
                        'count' => $count,
                        'status' => $count > 0 ? 'Importata' : 'Vuota',
                        'color' => $count > 0 ? 'success' : 'gray',
                        'last_log' => $lastLog ? [
                            'id' => $lastLog->id,
                            'date' => $lastLog->created_at->format('d/m/Y H:i'),
                            'file_name' => $lastLog->original_file_name,
                            'file_path' => $lastLog->file_path,
                            'processed' => $lastLog->rows_processed,
                            'created' => $lastLog->rows_created,
                            'updated' => $lastLog->rows_updated,
                            'ceduti' => $lastLog->rows_ceduti,
                        ] : null,
                    ];
                }),
        ];
    }

    /**
     * Azione per scaricare il file Excel di un'importazione specifica
     */
    public function downloadImportFile(int $logId)
    {
        $log = \App\Models\ImportLog::find($logId);
        
        if (!$log || !$log->file_path) {
            Notification::make()->title('File non trovato o percorso non registrato')->danger()->send();
            return null;
        }

        // Cerchiamo il file nei dischi possibili (livewire è quello di default per gli upload)
        foreach (['livewire', 'local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($log->file_path)) {
                return Storage::disk($disk)->download($log->file_path, $log->original_file_name);
            }
        }

        Notification::make()->title('File fisico non trovato sul server')->danger()->send();
        return null;
    }

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
                ->modalDescription('Carica il file Excel ufficiale (.xlsx) scaricato da Fantagazzetta.')
                ->form([
                    \Filament\Forms\Components\Select::make('season_id')
                        ->label('Stagione target')
                        ->options(\App\Models\Season::all()->mapWithKeys(fn($s) => [$s->id => \App\Helpers\SeasonHelper::formatYear($s->season_year)]))
                        ->required()
                        ->default(\App\Models\Season::where('is_current', true)->first()?->id ?? \App\Models\Season::orderBy('season_year', 'desc')->first()?->id),
                    FileUpload::make('roster_file')
                        ->label('File Listone (.xlsx)')
                        ->disk('livewire')       // disco dedicato (root: storage/app)
                        ->directory('imports')   // → storage/app/imports/<filename>.xlsx
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $paths    = $data['roster_file'] ?? [];
                    $relPath  = is_array($paths) ? (array_values($paths)[0] ?? null) : $paths;
                    $seasonId = (int)$data['season_id'];

                    if (!$relPath) {
                        Notification::make()->title('Nessun file caricato')->warning()->send();
                        return;
                    }

                    $this->runImport($relPath, $seasonId);
                }),
        ];
    }

    private function runImport(string $relPath, int $seasonId): void
    {
        $logDir  = storage_path('logs/Roster');
        $logPath = $logDir . '/RosterImport.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);

        $originalFileName = basename($relPath);
        $season = \App\Models\Season::find($seasonId);

        // ── Risoluzione disco + percorso ───────────────────────────────────────
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
            throw new \RuntimeException("File non trovato in nessuno storage disk.");
        }

        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info('📥  AVVIO IMPORTAZIONE LISTONE');
        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info("📄  File    : {$originalFileName}");
        $logger->info("📅  Stagione: {$season?->season_year}");
        $logger->info("💾  Disco   : {$diskName}");
        $logger->info("📂  FullPath: {$fullPath}");
        $logger->info("🕐  Ora     : " . now()->format('Y-m-d H:i:s'));

        $importLog = ImportLog::create([
            'original_file_name' => $originalFileName,
            'file_path'          => $relPath,
            'import_type'        => 'roster_quotazioni',
            'season_id'          => $seasonId,
            'status'             => 'in_corso',
            'details'            => "Avvio importazione Listone per stagione {$season?->season_year}.",
        ]);
        $logger->info("📋  ImportLog ID: {$importLog->id} creato (status: in_corso)");

        $mainImporter = new MainRosterImport($seasonId);

        try {
            // Nota: abbiamo rimosso il soft-delete globale di tutti i Player
            // dato che ora gestiamo un'anagrafica unica e sovrapponiamo i roster.
            
            // Passa il path relativo + nome disco a maatwebsite/excel
            Excel::import($mainImporter, $relPath, $diskName);

            $tuttiImporter = $mainImporter->getTuttiSheetImporter();

            $processed = $tuttiImporter ? $tuttiImporter->getProcessedCount() : 0;
            $created   = $tuttiImporter ? $tuttiImporter->getCreatedCount()   : 0;
            $transfers = $tuttiImporter ? $tuttiImporter->getTransferCount()  : 0;
            $confirmed = $tuttiImporter ? $tuttiImporter->getConfirmedCount() : 0;

            $formattedSeason = $season ? \App\Helpers\SeasonHelper::formatYear($season->season_year) : 'Unknown';

            // ── 3. GESTIONE CEDUTI (Cleanup) ───────────────────────────────────
            $processedIds = $tuttiImporter ? $tuttiImporter->getProcessedPlayerIds() : [];
            $cedutiCount = 0;

            if (!empty($processedIds)) {
                // 1. SOFT DELETE GLOBALE (Anagrafica)
                // Applichiamo la pulizia globale dei ceduti su OGNI importazione.
                // Chi non è nel listone viene considerato fuori dalla Serie A.
                $playersToSoftDelete = \App\Models\Player::whereNotIn('id', $processedIds)
                    ->whereNull('deleted_at')
                    ->get();

                $cedutiCount = $playersToSoftDelete->count();

                if ($cedutiCount > 0) {
                    $logger->info("🧹  PULIZIA GLOBALE CEDUTI: Trovati {$cedutiCount} calciatori non più presenti nel listone attuale ({$formattedSeason}).");
                    
                    foreach ($playersToSoftDelete as $player) {
                        $player->delete(); // Soft Delete (deleted_at)
                        $logger->info("   - [CEDUTO_GLOBALE] Anagrafica cassata: {$player->name} (non presente nel file attuale)");
                    }
                }
            }

            $importLog->status         = 'successo';
            $importLog->details        = "Importazione completata per stagione {$formattedSeason}. Processati: {$processed}, Creati: {$created}, Aggiornati: {$transfers}, Ceduti: {$cedutiCount}.";
            $importLog->rows_processed = $processed;
            $importLog->rows_created   = $created;
            $importLog->rows_updated   = $transfers; // Salviamo i trasferimenti come 'updated'
            $importLog->rows_ceduti    = $cedutiCount;
            $importLog->save();

            $logger->info("✅  COMPLETATO — Processati: {$processed} | Nuovi: {$created} | Trasferimenti: {$transfers} | Confermati: {$confirmed} | Ceduti: {$cedutiCount}");
            $logger->info("📋  ImportLog ID: {$importLog->id} aggiornato (status: successo)");
            $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Notification::make()
                ->title('Importazione completata!')
                ->body("Stagione: {$formattedSeason} | Nuovi: {$created} | Trasferimenti: {$transfers} | Confermati: {$confirmed} | Ceduti: {$cedutiCount}")
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
