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
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?string $navigationLabel = '1. Gestione Stagioni';
    protected static ?string $title = 'Gestione Stagione API';
    protected static ?int $navigationSort = 1;
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
            Action::make('setupTimeline')
                ->label('Inizializza Timeline Completa')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->action(function () {
                    $results = app(SeasonMonitorService::class)->initializeFullTimeline();
                    
                    if ($results['status'] === 'success') {
                        \Filament\Notifications\Notification::make()
                            ->title('Timeline Inizializzata')
                            ->body('La timeline delle stagioni è stata configurata cronologicamente.')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Errore Inizializzazione')
                            ->body($results['message'])
                            ->danger()
                            ->send();
                    }

                    $this->localSeasonState = Season::where('is_current', true)->first();
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
