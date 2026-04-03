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
                ->label('Step 2: Sync Storico Lookback (4 Anni - Tutte le Leghe)')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Questa operazione sincronizzerà le ultime 4 stagioni concluse per TUTTE le leghe configurate (Serie A e Serie B).')
                ->action(function (\App\Services\LeagueHistoryScraperService $service) {
                    $seasons = \App\Helpers\SeasonHelper::getCompletedLookbackSeasons(4);
                    $leagues = \App\Models\League::whereNotNull('fbref_id')->get();
                    
                    $totalCreated = 0;
                    $totalUpdated = 0;

                    foreach ($leagues as $league) {
                        foreach (array_keys($seasons) as $year) {
                            $result = $service->scrapeSeason((int) $year, true, $league);
                            if ($result['status'] === 'success') {
                                $totalCreated += $result['stats']['created'];
                                $totalUpdated += $result['stats']['updated'];
                            }
                        }
                    }

                    Notification::make()
                        ->title("Sync Globale Completato!")
                        ->body("Elaborate 4 stagioni per " . $leagues->count() . " leghe. Totale Creati: {$totalCreated}, Totale Aggiornati: {$totalUpdated}")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('fetch_history')
                ->label('Sincronizza Singola Stagione')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->form([
                    Select::make('seasonYear')
                        ->label('Stagione (Inizio Anno)')
                        ->options(\App\Helpers\SeasonHelper::getCompletedLookbackSeasons(4))
                        ->default(\App\Helpers\SeasonHelper::getCurrentSeason() - 1)
                        ->required(),
                    Select::make('league_id')
                        ->label('Lega')
                        ->options(\App\Models\League::whereNotNull('fbref_id')->pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data, \App\Services\LeagueHistoryScraperService $service) {
                    $year = (int) $data['seasonYear'];
                    $league = \App\Models\League::find($data['league_id']);
                    
                    $result = $service->scrapeSeason($year, true, $league);

                    if ($result['status'] === 'success') {
                        $stats = $result['stats'];
                        Notification::make()->title("Sincronizzazione {$league->name} {$year} completata!")
                            ->body("Creati: {$stats['created']}, Aggiornati: {$stats['updated']}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()->title("Sincronizzazione fallita per {$league->name} {$year}")
                            ->body($result['message'] ?? "Errore sconosciuto.")
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