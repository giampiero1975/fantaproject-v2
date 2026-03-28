<?php
namespace App\Filament\Resources\TeamHistoricalStandingResource\Pages;

use App\Filament\Resources\TeamHistoricalStandingResource;
use App\Services\TeamDataService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;

class ListTeamHistoricalStandings extends ListRecords
{
    protected static string $resource = TeamHistoricalStandingResource::class;

    protected function getHeaderWidgets(): array
    {
        return static::$resource::getWidgets();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetch_history')->label('Step 3: Popola Storico')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->form([
                    Select::make('seasonYear')->label('Quale stagione vuoi scaricare?')
                    ->options(function () {
                        $currentYear = (date('n') < 8) ? (int) date('Y') - 1 : (int) date('Y');
                        $years = [];
                        for ($i = 0; $i < 10; $i ++) {
                            $y = $currentYear - $i;
                            $years[$y] = $y;
                        }
                        return $years;
                    })
                    ->default((date('n') < 8) ? (int) date('Y') - 1 : (int) date('Y'))
                    ->required()
                ])
                ->action(function (array $data, \App\Services\LeagueHistoryScraperService $service) {
                    $year = (int) $data['seasonYear'];
                    $result = $service->scrapeSeason($year);

                    if ($result['status'] === 'success') {
                        $stats = $result['stats'];
                        Notification::make()->title("Sincronizzazione stagione {$year} completata!")
                            ->body("Creati: {$stats['created']}, Aggiornati: {$stats['updated']}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()->title("Sincronizzazione fallita per il {$year}")
                            ->body($result['message'] ?? "Errore sconosciuto. Controlla il log in storage/logs/history_import.log")
                            ->danger()
                            ->send();
                    }
            }),
            Actions\Action::make('check_coverage')->label('Verifica Copertura')
                ->icon('heroicon-o-table-cells')
                ->color('info')
                ->url(fn () => static::$resource::getUrl('coverage'))
        ];
    }
}