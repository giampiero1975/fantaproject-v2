<?php

namespace App\Filament\Resources\TeamHistoricalStandingResource\Pages;

use App\Filament\Resources\TeamHistoricalStandingResource;
use App\Services\TeamDataService;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CoverageStandings extends Page
{
    protected static string $resource = TeamHistoricalStandingResource::class;
    protected static string $view = 'filament.resources.team-historical-standing-resource.pages.coverage-standings';
    
    public array $seasons = [];
    public array $coverageData = [];
    
    public function mount(TeamDataService $service): void
    {
        // Usiamo la logica centralizzata per i 4 anni conclusi
        $this->seasons = array_keys(\App\Helpers\SeasonHelper::getCompletedLookbackSeasons(4));
        
        $this->coverageData = $service->getCoverageData($this->seasons);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('scrapeFromUrl')
            ->label('Sincronizza Stagione Specifica')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->form([
                Select::make('year')
                ->label('Stagione (Inizio Anno)')
                ->options(\App\Helpers\SeasonHelper::getCompletedLookbackSeasons(10))
                ->default(\App\Helpers\SeasonHelper::getCurrentSeason() - 1)
                ->required(),
                Select::make('division')
                ->label('Divisione')
                ->options(['A' => 'Serie A', 'B' => 'Serie B'])
                ->default('A')
                ->required(),
            ])
            ->action(function (array $data, TeamDataService $service) {
                try {
                    $year = (int)$data['year'];
                    $nextYear = $year + 1;
                    $div = $data['division'];
                    $compId = ($div === 'A') ? 11 : 18;
                    $divName = ($div === 'A') ? 'Serie-A' : 'Serie-B';
                    
                    // Composizione automatica dell'URL di FBref
                    $url = "https://fbref.com/en/comps/{$compId}/{$year}-{$nextYear}/{$year}-{$nextYear}-{$divName}-Stats";
                    
                    $service->scrapeFromFbrefUrl($url, $year, $div);
                    
                    Notification::make()->title("Sincronizzazione {$divName} {$year}/{$nextYear} completata!")->success()->send();
                    $this->mount($service); // Ricarica la matrice
                    
                } catch (\Exception $e) {
                    Notification::make()->title('Errore')->body($e->getMessage())->danger()->send();
                }
            }),
            Action::make('autoSync')
            ->label('Sincronizzazione Automatica Completa')
            ->icon('heroicon-o-bolt')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Questa operazione scaricherà le classifiche per tutte le stagioni configurate nella pagina, sia per la Serie A che per la Serie B, calcolando automaticamente gli URL corretti di FBref. L\'operazione potrebbe richiedere alcuni minuti a causa dei tempi di caricamento delle pagine scraper.')
            ->action(function (TeamDataService $service) {
                try {
                    $service->syncAllMissingCoverage($this->seasons);
                    
                    Notification::make()->title('Sincronizzazione batch completata!')->success()->send();
                    $this->mount($service);
                } catch (\Exception $e) {
                    Notification::make()->title('Errore durante la sincronizzazione')->body($e->getMessage())->danger()->send();
                }
            }),
        ];
    }
}