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
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        
        // Calcolo dinamico: Agosto come soglia per la stagione conclusa
        $lastConcludedYear = ($currentMonth < 8) ? $currentYear - 2 : $currentYear - 1;
        
        $this->seasons = [
            $lastConcludedYear,
            $lastConcludedYear - 1,
            $lastConcludedYear - 2,
            $lastConcludedYear - 3
        ];
        
        $this->coverageData = $service->getCoverageData($this->seasons);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('scrapeFromUrl')
            ->label('Sincronizza da URL FBref')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->form([
                TextInput::make('url')
                ->label('URL Competizione FBref')
                ->default('https://fbref.com/en/comps/18/2024-2025/2024-2025-Serie-B-Stats')
                ->required(),
                TextInput::make('year')
                ->label('Anno Stagione')
                ->numeric()
                ->default((int)date('Y') - 1)
                ->required(),
                Select::make('division')
                ->label('Divisione')
                ->options(['A' => 'Serie A', 'B' => 'Serie B'])
                ->required(),
            ])
            ->action(function (array $data, TeamDataService $service) {
                try {
                    $service->scrapeFromFbrefUrl($data['url'], (int)$data['year'], $data['division']);
                    
                    Notification::make()->title('Sincronizzazione completata!')->success()->send();
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