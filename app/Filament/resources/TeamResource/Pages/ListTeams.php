<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Filament\Resources\TeamResource\Widgets\TeamGuideWidget;
use App\Models\Season;
use App\Models\League;
use App\Services\TeamDataService;
use App\Services\LeagueHistoryScraperService;
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
                ->modalHeading('Configurazione Sincronizzazione Squadre')
                ->modalDescription('Seleziona la stagione (già creata in Gestione Stagione) e la sorgente dei dati.')
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
                    
                    Forms\Components\Radio::make('source')
                        ->label('Sorgente Dati')
                        ->options([
                            'api' => 'API Ufficiale (Football-Data.org)',
                            'fbref' => 'FBref Scraper (Dati Storici / Tier)',
                        ])
                        ->default('api')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $year = (int) $data['season_year'];
                    $source = $data['source'];

                    // Assicuriamoci che la Lega Serie A (api_id 2019) esista con codice 'ITA'
                    League::firstOrCreate(
                        ['api_id' => 2019],
                        ['name' => 'Serie A', 'country_code' => 'ITA']
                    );

                    try {
                        if ($source === 'api') {
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
                            
                            $sourceLabel = 'API Ufficiale';
                        } else {
                            // Sync via FBref Scraper
                            $result = app(LeagueHistoryScraperService::class)->scrapeSeason($year);
                            if ($result['status'] === 'error') {
                                throw new \Exception($result['message']);
                            }
                            $sourceLabel = "FBref Scraper";
                        }

                        Notification::make()
                            ->title('Sincronizzazione completata!')
                            ->body("Dati per la stagione " . \App\Helpers\SeasonHelper::formatYear($year) . " aggiornati con successo via {$sourceLabel}.")
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