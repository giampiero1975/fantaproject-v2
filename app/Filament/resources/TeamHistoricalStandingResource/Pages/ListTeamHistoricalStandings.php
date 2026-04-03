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
            Actions\Action::make('bulk_sync_history')
                ->label('Step 2: Sync Storico Lookback (4 Anni Conclusi)')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (\App\Services\LeagueHistoryScraperService $service) {
                    $seasons = \App\Helpers\SeasonHelper::getCompletedLookbackSeasons(4);
                    $totalCreated = 0;
                    $totalUpdated = 0;

                    foreach (array_keys($seasons) as $year) {
                        $result = $service->scrapeSeason((int) $year, true);
                        if ($result['status'] === 'success') {
                            $totalCreated += $result['stats']['created'];
                            $totalUpdated += $result['stats']['updated'];
                        }
                    }

                    Notification::make()
                        ->title("Sync Globale Completato!")
                        ->body("Elaborate 4 stagioni. Record Creati: {$totalCreated}, Record Aggiornati: {$totalUpdated}")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('fetch_history')->label('Sincronizza Singola Stagione')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->form([
                    Select::make('seasonYear')->label('Quale stagione vuoi scaricare?')
                    ->options(\App\Helpers\SeasonHelper::getLookbackSeasons(10))
                    ->default(\App\Helpers\SeasonHelper::getCurrentSeason())
                    ->required()
                ])
                ->action(function (array $data, \App\Services\LeagueHistoryScraperService $service) {
                    $year = (int) $data['seasonYear'];
                    $result = $service->scrapeSeason($year, true);

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