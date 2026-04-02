<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Season;
use App\Services\SeasonMonitorService;
use Filament\Actions\Action;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;

class ManageSeason extends Page implements HasTable
{
    use InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Impostazioni di Sistema';
    protected static ?string $navigationLabel = 'Gestione Stagione';
    protected static ?string $title = 'Gestione Stagione API';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.manage-season';

    public $localSeasonState;
    public $apiSeasonState = null;
    public $lookbackStatus = null;

    public function mount()
    {
        $this->localSeasonState = Season::where('is_current', true)->first();
        $this->computeLookback();
    }

    public function computeLookback()
    {
        $this->lookbackStatus = app(SeasonMonitorService::class)->getHistoricalLookback();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncSeasons')
                ->label('Sincronizza Stagioni da Api')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->action(function () {
                    $this->forceCheck();
                    
                    // Se rilevata nuova stagione come UPDATE dal monitor, inizializzala
                    if (!$this->localSeasonState && $this->apiSeasonState['status'] === SeasonMonitorService::STATUS_UPDATE) {
                        $apiData = $this->apiSeasonState['api_data'];
                        $year = (int) substr($apiData['startDate'], 0, 4);
                        
                        Season::updateOrCreate(
                            ['api_id' => $this->apiSeasonState['api_id']],
                            [
                                'season_year' => $year,
                                'start_date' => $apiData['startDate'],
                                'end_date' => $apiData['endDate'] ?? null,
                                'is_current' => true,
                            ]
                        );

                        \App\Models\ImportLog::create([
                            'import_type' => 'SEASON_INIT',
                            'original_file_name' => 'ManageSeason Page',
                            'status' => 'successo',
                            'details' => "Inizializzata stagione {$year} (API ID: {$this->apiSeasonState['api_id']})",
                            'rows_created' => 1,
                            'rows_processed' => 1,
                        ]);
                        
                        $this->localSeasonState = Season::where('is_current', true)->first();
                        $this->computeLookback();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Stagione Inizializzata')
                            ->body("Rilevata e configurata la stagione {$year} come attuale.")
                            ->success()
                            ->send();
                    }
                }),
            Action::make('bootstrapHistory')
                ->label('Inizializza Lookback 4 Anni')
                ->color('warning')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->requiresConfirmation()
                ->modalHeading('Generazione Automatica Storico')
                ->modalDescription('Verranno creati i 4 contenitori stagionali precedenti a quella in corso (es. 2021-2024). Questa operazione NON scarica squadre.')
                ->visible(fn () => ($this->lookbackStatus['exists_count'] ?? 0) < ($this->lookbackStatus['target_count'] ?? 4))
                ->action(function () {
                    if (!$this->localSeasonState) {
                        \Filament\Notifications\Notification::make()
                            ->title('Stagione Corrente Mancante')
                            ->body('Devi prima sincronizzare la stagione attuale per definire l\'anno di riferimento.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $results = app(\App\Services\SeasonMonitorService::class)->bootstrapHistory();
                    
                    if ($results['status'] === 'success') {
                        \Filament\Notifications\Notification::make()
                            ->title('Lookback Inizializzato')
                            ->body('Creati i contenitori stagionali per il monitoraggio storico.')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Errore Bootstrap')
                            ->body($results['message'])
                            ->danger()
                            ->send();
                    }

                    $this->computeLookback();
                }),
        ];
    }

    public function forceCheck()
    {
        $monitor = app(SeasonMonitorService::class);
        $this->apiSeasonState = $monitor->checkNow(null, null, true);
        $this->computeLookback();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Season::query()->orderBy('season_year', 'desc'))
            ->columns([
                TextColumn::make('api_id')->label('ID API')->sortable(),
                TextColumn::make('season_year')->label('Stagione (Anno)')->sortable(),
                TextColumn::make('start_date')->label('Inizio')->date('d/m/Y')->sortable(),
                TextColumn::make('end_date')->label('Fine')->date('d/m/Y')->sortable(),
                BadgeColumn::make('data_status')
                    ->label('Stato Dati')
                    ->getStateUsing(fn (Season $record) => app(SeasonMonitorService::class)->getLocalStatus($record)['label'])
                    ->color(fn (Season $record) => app(SeasonMonitorService::class)->getLocalStatus($record)['color'])
                    ->icon(fn (Season $record) => app(SeasonMonitorService::class)->getLocalStatus($record)['icon']),
                
                BadgeColumn::make('sync_method')
                    ->label('Metodo Sync Consigliato')
                    ->getStateUsing(fn (Season $record) => $record->season_year >= 2023 ? 'API Ufficiali' : 'Scraper FBref')
                    ->color(fn (Season $record) => $record->season_year >= 2023 ? 'success' : 'warning'),

                IconColumn::make('is_current')->label('Attiva')->boolean(),
            ])
            ->actions([]);
    }
}
