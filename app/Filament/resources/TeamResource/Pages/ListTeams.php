<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Filament\Resources\TeamResource\Widgets\TeamGuideWidget;
use App\Models\Season;
use App\Models\League;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Forms;
use Illuminate\Support\Facades\Artisan;

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
            Actions\Action::make('sync_teams_unified')
                ->label('Sincronizza Squadre')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->modalHeading('Sincronizzazione Squadre (API Ufficiale)')
                ->modalDescription('Scarica le squadre della stagione selezionata usando Football-Data.org.')
                ->form([
                    Forms\Components\Select::make('season_year')
                        ->label('Stagione')
                        ->options(function() {
                            return \App\Models\Season::whereDoesntHave('teams')
                                ->orderBy('season_year', 'desc')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->season_year => \App\Helpers\SeasonHelper::formatYear($s->season_year)
                                ]);
                        })
                        ->placeholder('Seleziona una stagione da scaricare...')
                        ->required()
                        ->hint('Vengono mostrate solo le stagioni senza squadre censite.'),
                ])
                ->action(function (array $data) {
                    $year = (int) $data['season_year'];

                    // Assicuriamoci che la Lega Serie A (api_id 2019) esista con codice 'ITA'
                    League::firstOrCreate(
                        ['api_id' => 2019],
                        ['name' => 'Serie A', 'country_code' => 'ITA']
                    );

                    try {
                        // Sync via Official API Artisan Command
                        $exitCode = Artisan::call('football:sync-serie-a', ['season_year' => $year]);
                        
                        if ($exitCode !== 0) {
                            Notification::make()
                                ->title('Errore sincronizzazione API!')
                                ->body("L'API ufficiale ha risposto con un errore (probabilmente stagione non inclusa nel piano).")
                                ->danger()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title('Sincronizzazione completata!')
                            ->body("Dati per la stagione " . \App\Helpers\SeasonHelper::formatYear($year) . " aggiornati con successo via API Ufficiale.")
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
            Actions\Action::make('align_fbref_bulk')
                ->label('Allinea FBref (Massivo)')
                ->icon('heroicon-o-link')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Allineamento FBref Master')
                ->modalDescription('Questo comando cercherà di mappare automaticamente tutte le squadre Serie A che ancora non hanno un ID FBref. Attenzione: consuma crediti Proxy.')
                ->form([
                    Forms\Components\Select::make('season_year')
                        ->label('Stagione Target')
                        ->options(\App\Helpers\SeasonHelper::getPresentSeasons())
                        ->placeholder('Tutte le stagioni rilevate')
                        ->hint('Se vuoto, allinea tutti i team mancanti indipendentemente dalla stagione.'),
                ])
                ->action(function (array $data) {
                    try {
                        $seasonId = $data['season_year'];
                        $year = $seasonId ? \App\Models\Season::find($seasonId)?->season_year : null;
                        
                        $service = app(\App\Services\TeamFbrefAlignmentService::class);
                        $result = $service->align($year);

                        if ($result['status'] === 'success') {
                            Notification::make()
                                ->title('Allineamento Completato')
                                ->body("Match riusciti: {$result['matched']}. Errori: {$result['errors']}. Chiamate Proxy: {$result['proxy_calls']}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Errore Allineamento')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore Tecnico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}