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
    protected static ?string $navigationLabel = '4. Tier Engine';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Tier Engine';
    protected static string $view = 'filament.pages.tier-squadre';

    public function getTeams(): \Illuminate\Support\Collection
    {
        $currentSeasonId = \App\Models\Season::where('is_current', true)->value('id');
        
        $activeTeamIds = \Illuminate\Support\Facades\DB::table('team_season')
            ->where('season_id', $currentSeasonId)
            ->where('is_active', true)
            ->pluck('team_id');

        return DB::table('teams')
            ->whereIn('id', $activeTeamIds)
            ->orderBy('tier_globale')
            ->orderBy('posizione_media_storica')
            ->get(['id', 'name', 'tier_globale as tier', 'logo_url as crest_url', 'posizione_media_storica as posizione_media']);
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
                ->form(function () {
                    $configDefault = config('projection_settings.tier_calculation.lookback_seasons', 4);
                    
                    // Recupero anni dinamici
                    $availableYears = DB::table('team_historical_standings')
                        ->distinct()
                        ->orderByDesc('season_year')
                        ->pluck('season_year')
                        ->toArray();
                    
                    $totalAvailable = count($availableYears);
                    $latestYear     = !empty($availableYears) ? $availableYears[0] : (\App\Helpers\SeasonHelper::getCurrentSeason() - 1);
                    
                    $options = [];
                    // Mostriamo le opzioni dal massimo disponibile scendendo
                    for ($i = $totalAvailable; $i >= 1; $i--) {
                        $startYear = $latestYear - $i + 1;
                        
                        // Usiamo il nuovo helper per formattare l'intervallo standard (Dal 2021/22 al 2024/25)
                        $rangeLabel = \App\Helpers\SeasonHelper::formatRange($startYear, $latestYear);
                        
                        $label = "$rangeLabel (" . ($i === 1 ? "1 stagione" : "$i stagioni") . ")";
                        
                        if ($i === $configDefault) {
                            $label .= " (default)";
                        }
                        
                        $options[$i] = $label;
                    }

                    return [
                        Select::make('lookback_years')
                            ->label('Stagioni da analizzare')
                            ->options($options)
                            ->default($configDefault)
                            ->required(),
                    ];
                })
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
