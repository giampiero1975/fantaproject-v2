<?php

namespace App\Filament\Pages;

use App\Services\TeamDataService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class TierSquadre extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = '3. Tier Squadre';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Tier Squadre';
    protected static string $view = 'filament.pages.tier-squadre';

    public function getTeams(): \Illuminate\Support\Collection
    {
        return DB::table('teams')
            ->where('serie_a_team', 1)
            ->orderBy('tier')
            ->orderBy('posizione_media')
            ->get(['id', 'name', 'tier', 'crest_url', 'posizione_media']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ricalcola_tiers')
                ->label('Ricalcola Tier Squadre')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Ricalcola Tier Squadre')
                ->modalDescription('Analizza le classifiche storiche degli ultimi anni e ricalcola automaticamente il Tier (1-5) per ogni squadra di Serie A. Tier 1 = Top club (es. Inter, Milan), Tier 5 = coda classifica cronica. Il Tier è fondamentale per le proiezioni dei giocatori.')
                ->form([
                    Select::make('lookback_years')
                        ->label('Stagioni da analizzare')
                        ->options([
                            3 => '3 stagioni',
                            4 => '4 stagioni',
                            5 => '5 stagioni (default)',
                            7 => '7 stagioni',
                        ])
                        ->default(5)
                        ->required(),
                ])
                ->action(function (array $data, TeamDataService $service) {
                    try {
                        $result = $service->updateTeamTiers((int) $data['lookback_years']);
                        Notification::make()
                            ->title('Tier aggiornati!')
                            ->body("Squadre aggiornate: {$result['updated']}, saltate: {$result['skipped']}.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore nel calcolo')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
