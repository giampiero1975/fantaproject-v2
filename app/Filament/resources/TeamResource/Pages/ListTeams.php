<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Filament\Resources\TeamResource\Widgets\TeamGuideWidget;
use App\Services\TeamDataService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TeamGuideWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_teams')
                ->label('Sincronizza Squadre da API')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sincronizza Anagrafica Squadre')
                ->modalDescription('Recupera i nomi ufficiali, i loghi e gli ID delle squadre da Football-Data.org. Questa operazione aggiornerà le squadre già presenti e aggiungerà quelle mancanti.')
                ->action(function (TeamDataService $service) {
                    try {
                        $result = $service->importTeamsFromApi();
                        Notification::make()
                            ->title('Sincronizzazione completata!')
                            ->body("Squadre create: {$result['created']}, aggiornate: {$result['updated']}.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore durante la sincronizzazione')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

}