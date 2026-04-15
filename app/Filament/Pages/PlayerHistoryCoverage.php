<?php

namespace App\Filament\Pages;

use App\Models\Player;
use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\HistoricalPlayerStat;
use App\Helpers\SeasonHelper;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\HtmlString;

class PlayerHistoryCoverage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?string $title = '9. Importa Storico Stats';
    protected static ?string $navigationLabel = '9. Importa Storico Stats';
    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.player-history-coverage';
    
    public ?int $activeSeasonId = null;
    public bool $auditMode = true;

    public function mount(): void
    {
        // Default sulla stagione corrente
        $this->activeSeasonId = SeasonHelper::getCurrentSeasonId();
    }

    public function toggleMode(): void
    {
        $this->auditMode = !$this->auditMode;
    }

    public function getSeasonsForTabs(): array
    {
        $lookback = SeasonHelper::getLookbackSeasons();
        return \App\Models\Season::whereIn('season_year', array_keys($lookback))
            ->orderBy('season_year', 'desc')
            ->get()
            ->mapWithKeys(fn($s) => [
                $s->id => SeasonHelper::formatYear($s->season_year) . ($s->is_current ? ' (Oggi)' : '')
            ])
            ->toArray();
    }

    public function table(Table $table): Table
    {
        $query = Player::withTrashed()
            ->whereHas('rosters', fn($q) => $q->where('season_id', $this->activeSeasonId));

        if ($this->auditMode) {
            $query->whereDoesntHave('historicalStats', fn($q) => $q->where('season_id', $this->activeSeasonId));
        } else {
            $query->whereHas('historicalStats', fn($q) => $q->where('season_id', $this->activeSeasonId));
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('fanta_platform_id')
                    ->label('ID Fanta')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->sortable()
                    ->visible($this->auditMode),
                TextColumn::make('latestRoster.team.name')
                    ->label('Squadra')
                    ->visible($this->auditMode),
                
                // Colonne Sartoriali (Visibili solo in modalità dati)
                TextColumn::make('games_played')
                    ->label('Pv')
                    ->getStateUsing(fn($record) => $record->historicalStats()->where('season_id', $this->activeSeasonId)->value('games_played'))
                    ->visible(!$this->auditMode),
                TextColumn::make('average_rating')
                    ->label('Mv')
                    ->getStateUsing(fn($record) => $record->historicalStats()->where('season_id', $this->activeSeasonId)->value('average_rating'))
                    ->visible(!$this->auditMode),
                TextColumn::make('fanta_average')
                    ->label('Fm')
                    ->getStateUsing(fn($record) => $record->historicalStats()->where('season_id', $this->activeSeasonId)->value('fanta_average'))
                    ->visible(!$this->auditMode),
                TextColumn::make('goals')
                    ->label('Gol')
                    ->getStateUsing(fn($record) => $record->historicalStats()->where('season_id', $this->activeSeasonId)->value('goals'))
                    ->visible(!$this->auditMode),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importHistoricalStats')
                ->label('Importa Storico Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->modalHeading('Importazione Storico Fantacalcio.it')
                ->modalDescription('Seleziona il file Excel (formato scaricabile da Fantacalcio.it) e l\'anno di riferimento.')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->required()
                        ->disk('livewire') 
                        ->directory('imports')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']),
                    \Filament\Forms\Components\Select::make('season_id')
                        ->label('Seleziona Stagione Storica')
                        ->options(
                            \App\Models\Season::whereIn('season_year', array_keys(\App\Helpers\SeasonHelper::getLookbackSeasons()))
                                ->orderBy('season_year', 'desc')
                                ->get()
                                ->mapWithKeys(fn($s) => [
                                    $s->id => \App\Helpers\SeasonHelper::formatYear($s->season_year) . ($s->is_current ? ' (In Corso)' : '')
                                ])
                        )
                        ->required()
                        ->native(false)
                        ->placeholder('Scegli l\'anno...'),
                ])
                ->action(function (array $data) {
                    $filePath = \Illuminate\Support\Facades\Storage::disk('livewire')->path($data['file']);
                    $seasonId = $data['season_id'];
                    $seasonYear = \App\Models\Season::find($seasonId)?->season_year ?? 'N/D';

                    try {
                        // Logging dedicato
                        $logFile = 'storage/logs/HistoricalStats/Import.log';
                        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($logFile));
                        \Illuminate\Support\Facades\File::put($logFile, "");
                        
                        $logger = new \Monolog\Logger('HistoricalStats');
                        $logger->pushHandler(new \Monolog\Handler\StreamHandler($logFile, \Monolog\Logger::DEBUG));

                        // Esecuzione diretta per catturare i contatori
                        $importer = new \App\Imports\HistoricalStatsImport((int)$seasonId, $logger);
                        \Maatwebsite\Excel\Facades\Excel::import($importer, $filePath);

                        $total   = $importer->getExcelRowCount();
                        $success = $importer->getMatchSuccessCount();
                        $failed  = $importer->getMatchFailedCount();

                        Notification::make()
                            ->title("Importazione Stagione {$seasonYear} Completata")
                            ->success()
                            ->persistent()
                            ->body(new HtmlString("
                                <div class='space-y-4 pt-2'>
                                    <p class='text-sm text-gray-500 font-medium border-b pb-2 mb-2'>Riepilogo Elaborazione Analitica</p>
                                    <table class='w-full text-left text-sm'>
                                        <tbody>
                                            <tr class='border-b border-gray-100'>
                                                <td class='py-2 text-gray-600'>Righe Elaborate (Excel)</td>
                                                <td class='py-2 text-right font-bold text-gray-900'>{$total}</td>
                                            </tr>
                                            <tr class='border-b border-gray-100'>
                                                <td class='py-2 text-gray-600 font-semibold'>Match Riusciti (ID + Roster)</td>
                                                <td class='py-2 text-right font-bold text-success-600'>{$success}</td>
                                            </tr>
                                            <tr>
                                                <td class='py-2 text-gray-600'>Match Falliti (ID assente)</td>
                                                <td class='py-2 text-right font-bold text-danger-600'>{$failed}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class='mt-4 p-2 bg-gray-50 rounded-lg text-xs text-gray-500 italic'>
                                        * I match falliti includono giocatori del file 2021 non presenti nel tuo roster attuale o in anagrafica.
                                    </div>
                                </div>
                            "))
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore durante l\'importazione')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CoverageStats::class,
        ];
    }
}
