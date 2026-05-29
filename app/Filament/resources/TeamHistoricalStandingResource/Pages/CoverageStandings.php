<?php

namespace App\Filament\Resources\TeamHistoricalStandingResource\Pages;

use App\Filament\Resources\TeamHistoricalStandingResource;
use App\Services\TeamDataService;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

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
                ->options(\App\Helpers\SeasonHelper::getCompletedLookbackSeasons(4))
                ->default(\App\Helpers\SeasonHelper::getCurrentSeason() - 1)
                ->required(),
                Select::make('league_id')
                ->label('Divisione')
                ->options(\App\Models\League::pluck('name', 'id'))
                ->default(fn() => \App\Models\League::where('name', 'Serie A')->value('id'))
                ->required(),
            ])
            ->action(function (array $data, TeamDataService $service) {
                try {
                    $year = (int)$data['year'];
                    $league = \App\Models\League::find($data['league_id']);

                    if (!$league || empty($league->fbref_id)) {
                        throw new \Exception("Lega non configurata correttamente.");
                    }

                    $slug = str_replace(' ', '-', $league->name);
                    $nextYear = $year + 1;
                    $url = "https://fbref.com/en/comps/{$league->fbref_id}/{$year}-{$nextYear}/{$year}-{$nextYear}-{$slug}-Stats";
                    
                    $service->scrapeFromFbrefUrl($url, $year, $league->name);
                    
                    Notification::make()->title("Sincronizzazione {$league->name} {$year}/{$nextYear} completata!")->success()->send();
                    $this->mount($service);
                    
                } catch (\Exception $e) {
                    Notification::make()->title('Errore')->body($e->getMessage())->danger()->send();
                }
            }),
        ];
    }
}