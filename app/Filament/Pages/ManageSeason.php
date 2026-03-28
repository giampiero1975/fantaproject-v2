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

    public function mount()
    {
        $this->localSeasonState = Season::where('is_current', true)->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkApi')
                ->label('Forza Verifica Stagione su API')
                ->color('primary')
                ->icon('heroicon-o-arrow-path')
                ->action('forceCheck'),
                
            Action::make('initializeSeason')
                ->label(fn () => "Inizializza Nuova Stagione " . ($this->apiSeasonState['api_data']['startDate'] ?? ''))
                ->color('success')
                ->icon('heroicon-o-play')
                // visible ONLY if API says UPDATE
                ->visible(fn () => isset($this->apiSeasonState['status']) && $this->apiSeasonState['status'] === SeasonMonitorService::STATUS_UPDATE)
                ->requiresConfirmation()
                ->action('startStep1')
        ];
    }

    public function forceCheck()
    {
        $monitor = app(SeasonMonitorService::class);
        $this->apiSeasonState = $monitor->checkNow(null, null, true);
    }

    public function startStep1()
    {
        if (!$this->apiSeasonState || empty($this->apiSeasonState['api_data']['startDate'])) {
            \Filament\Notifications\Notification::make()
                ->title('Errore Inizializzazione')
                ->body('Dati API non disponibili. Esegui la verifica prima.')
                ->danger()
                ->send();
            return;
        }

        $apiData = $this->apiSeasonState['api_data'];
        $seasonYear = (int) substr($apiData['startDate'], 0, 4);

        Season::query()->update(['is_current' => false]);

        Season::updateOrCreate(
            ['id' => $this->apiSeasonState['api_id']],
            [
                'start_date' => $apiData['startDate'],
                'end_date' => $apiData['endDate'] ?? null,
                'season_year' => $seasonYear,
                'is_current' => true,
            ]
        );

        \Illuminate\Support\Facades\Artisan::call('football:sync-serie-a', [
            'season_year' => $seasonYear
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Sincronizzazione Avviata')
            ->body('Generata correttamente la stagione ' . $seasonYear)
            ->success()
            ->send();

        $this->localSeasonState = Season::where('is_current', true)->first();
        $this->forceCheck();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Season::query()->orderBy('season_year', 'desc'))
            ->columns([
                TextColumn::make('id')->label('ID (API)')->sortable(),
                TextColumn::make('season_year')->label('Stagione (Anno)')->sortable(),
                TextColumn::make('start_date')->label('Inizio')->date('d/m/Y')->sortable(),
                TextColumn::make('end_date')->label('Fine')->date('d/m/Y')->sortable(),
                IconColumn::make('is_current')->label('Attiva')->boolean(),
            ]);
    }
}
