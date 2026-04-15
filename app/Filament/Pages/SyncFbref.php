<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;
use App\Models\Team;
use App\Helpers\SeasonHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Services\ProxyManagerService;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\TeamSeason;

class SyncFbref extends Page implements HasForms, HasActions, HasTable
{
    use InteractsWithForms;
    use InteractsWithActions;
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = '8. Sync FBref';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 8;
    protected static ?string $title           = 'Sincronizzazione Dati FBref (Scraping)';
    protected static string  $view            = 'filament.pages.sync-fbref';
    protected static ?string $slug            = 'sync-fbref';

    public ?int $selectedSeasonYear = null;
    public ?int $selectedTeamId = null;

    protected function getHeaderWidgets(): array
    {
        return [
            // Widget rimosso (spostato in Proxy Services)
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Filtri di Sincronizzazione Chirurgica')
                ->description('Seleziona la stagione per un sync massivo o una specifica squadra per un intervento mirato.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('selectedSeasonYear')
                                ->label('1. Stagione')
                                ->options(SeasonHelper::getLookbackSeasons(4))
                                ->required()
                                ->live()
                                ->helperText(function (Get $get): ?string {
                                    $seasonYear = $get('selectedSeasonYear');
                                    if (!$seasonYear) {
                                        return null;
                                    }

                                    $teamId = $get('selectedTeamId');
                                    $proxyManager = app(ProxyManagerService::class);
                                    $bestProxy = $proxyManager->getBestProxy();
                                    $jsCost = $bestProxy ? $bestProxy->js_cost : 10;

                                    if ($teamId) {
                                        $numCalls = 1;
                                        $label = "1 squadra selezionata";
                                    } else {
                                        $numCalls = TeamSeason::whereHas('season', fn($q) => $q->where('season_year', $seasonYear))
                                            ->where('league_id', 1) // Serie A
                                            ->count() ?: 20; // Fallback a 20 se non ancora importate
                                        $label = "{$numCalls} squadre della stagione";
                                    }
                                    
                                    $estimatedCredits = $numCalls * $jsCost;

                                    return "Crediti stimati necessari: {$estimatedCredits} ({$label} × {$jsCost} " . ($bestProxy?->name ?? 'Proxy') . ")";
                                })
                                ->afterStateUpdated(fn () => $this->selectedTeamId = null),
                            Select::make('selectedTeamId')
                                ->label('2. Squadra (Opzionale)')
                                ->placeholder('Lascia vuoto per tutta la stagione')
                                ->options(fn (Get $get) => 
                                    Team::query()
                                        ->whereHas('teamSeasons', fn($q) => 
                                            $q->whereHas('season', fn($s) => $s->where('season_year', $get('selectedSeasonYear')))
                                              ->whereHas('league', fn($l) => $l->where('league_id', 1)) // League ID 1 = Serie A
                                        )
                                        ->whereNotNull('fbref_url')
                                        ->oldest('name')
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->live()
                                ->disabled(fn (Get $get) => !$get('selectedSeasonYear')),
                        ])
                ])
        ];
    }


    public function getSeasonsCoverageRows()
    {
        $rosterAgg = PlayerSeasonRoster::query()
            ->selectRaw('season_id, COUNT(DISTINCT player_season_roster.player_id) as total_players')
            ->join('players', 'players.id', '=', 'player_season_roster.player_id')
            ->whereNull('players.deleted_at')
            ->groupBy('season_id');

        $mappedAgg = PlayerSeasonRoster::query()
            ->selectRaw('season_id, COUNT(DISTINCT player_season_roster.player_id) as mapped_players')
            ->join('players', 'players.id', '=', 'player_season_roster.player_id')
            ->whereNull('players.deleted_at')
            ->whereNotNull('players.fbref_id')
            ->groupBy('season_id');

        return Season::query()
            ->orderByDesc('season_year')
            ->leftJoinSub($rosterAgg, 'roster_agg', fn ($join) => $join->on('seasons.id', '=', 'roster_agg.season_id'))
            ->leftJoinSub($mappedAgg, 'mapped_agg', fn ($join) => $join->on('seasons.id', '=', 'mapped_agg.season_id'))
            ->get([
                'seasons.id',
                'seasons.season_year',
                DB::raw('COALESCE(roster_agg.total_players, 0) as total_players'),
                DB::raw('COALESCE(mapped_agg.mapped_players, 0) as mapped_players'),
            ])
            ->map(function ($row) {
                $total = (int) ($row->total_players ?? 0);
                $mapped = (int) ($row->mapped_players ?? 0);
                $pct = $total > 0 ? round(($mapped / $total) * 100, 1) : 0.0;

                return [
                    'season_id' => (int) $row->id,
                    'season_label' => SeasonHelper::formatYear((int) $row->season_year),
                    'total_players' => $total,
                    'mapped_players' => $mapped,
                    'coverage_pct' => $pct,
                ];
            });
    }

    public function getTeamsCoverageRows()
    {
        if (!$this->selectedSeasonYear) {
            return collect();
        }

        $seasonId = Season::query()
            ->where('season_year', (int) $this->selectedSeasonYear)
            ->value('id');

        if (!$seasonId) {
            return collect();
        }

        $totalPlayersSub = PlayerSeasonRoster::query()
            ->selectRaw('COUNT(DISTINCT player_season_roster.player_id)')
            ->join('players', 'players.id', '=', 'player_season_roster.player_id')
            ->whereNull('players.deleted_at')
            ->where('player_season_roster.season_id', $seasonId)
            ->whereColumn('player_season_roster.team_id', 'teams.id');

        $mappedPlayersSub = PlayerSeasonRoster::query()
            ->selectRaw('COUNT(DISTINCT player_season_roster.player_id)')
            ->join('players', 'players.id', '=', 'player_season_roster.player_id')
            ->whereNull('players.deleted_at')
            ->whereNotNull('players.fbref_id')
            ->where('player_season_roster.season_id', $seasonId)
            ->whereColumn('player_season_roster.team_id', 'teams.id');

        return Team::query()
            ->whereHas('teamSeasons', fn (Builder $q) => $q->where('season_id', $seasonId)->where('league_id', 1))
            ->when($this->selectedTeamId, fn (Builder $q) => $q->where('teams.id', $this->selectedTeamId))
            ->select('teams.id', 'teams.name')
            ->selectSub($totalPlayersSub, 'total_players')
            ->selectSub($mappedPlayersSub, 'mapped_players')
            ->orderBy('teams.name')
            ->get()
            ->map(function ($row) {
                $total = (int) ($row->total_players ?? 0);
                $mapped = (int) ($row->mapped_players ?? 0);
                $pct = $total > 0 ? round(($mapped / $total) * 100, 1) : 0.0;

                return [
                    'team_id' => (int) $row->id,
                    'team_name' => (string) $row->name,
                    'total_players' => $total,
                    'mapped_players' => $mapped,
                    'coverage_pct' => $pct,
                ];
            });
    }

    public function updatedSelectedSeasonYear()
    {
        $this->selectedTeamId = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function() {
                if (!$this->selectedTeamId) {
                    return PlayerSeasonRoster::query()->whereRaw('1 = 0');
                }

                return PlayerSeasonRoster::query()
                    ->whereHas('player', function ($q) {
                        $q->withTrashed()
                          ->where(function ($sub) {
                              $sub->whereNull('deleted_at')
                                  ->orWhereNotNull('fanta_platform_id');
                          });
                    })
                    ->with(['player' => fn($q) => $q->withTrashed(), 'team'])
                    ->whereHas('season', fn($q) => $q->where('season_year', $this->selectedSeasonYear))
                    ->where('team_id', $this->selectedTeamId);
            })
            ->columns([
                TextColumn::make('player.name')
                    ->label('Giocatore')
                    ->description(fn ($record) => $record->player?->trashed() ? 'Non a Listone / Ceduto' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->player?->trashed() ? 'Ceduto' : 'Attivo')
                    ->color(fn ($state) => $state === 'Attivo' ? 'success' : 'danger')
                    ->icon(fn ($state) => $state === 'Attivo' ? 'heroicon-m-check-badge' : 'heroicon-m-x-circle'),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge(),
                TextColumn::make('player.fbref_id')
                    ->label('FBref ID')
                    ->placeholder('Mancante')
                    ->copyable()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->weight(fn ($state) => $state ? 'bold' : 'normal'),
                IconColumn::make('has_url')
                    ->label('URL')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->player->fbref_url))
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->defaultSort('player.name')
            ->emptyStateHeading('Seleziona una squadra per vedere il roster');
    }


    public function getStats(): array
    {
        if (!$this->selectedSeasonYear || !$this->selectedTeamId) {
            return [];
        }

        $total = PlayerSeasonRoster::query()
            ->whereHas('season', fn($q) => $q->where('season_year', $this->selectedSeasonYear))
            ->where('team_id', $this->selectedTeamId)
            ->count();

        $mapped = PlayerSeasonRoster::query()
            ->whereHas('season', fn($q) => $q->where('season_year', $this->selectedSeasonYear))
            ->where('team_id', $this->selectedTeamId)
            ->whereHas('player', fn($q) => $q->whereNotNull('fbref_id'))
            ->count();

        $pct = $total > 0 ? round(($mapped / $total) * 100, 1) : 0;

        return [
            [
                'label' => 'Giocatori Team',
                'value' => $total,
                'color' => 'gray',
            ],
            [
                'label' => 'Mappati FBref',
                'value' => $mapped,
                'color' => 'success',
            ],
            [
                'label' => 'Copertura %',
                'value' => $pct . '%',
                'color' => $pct > 90 ? 'success' : ($pct > 50 ? 'warning' : 'danger'),
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('syncFbref')
                ->label(fn() => $this->selectedTeamId ? 'Sync Team' : 'Sync Massivo Stagione')
                ->icon('heroicon-o-play-circle')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Conferma Sincronizzazione FBref')
                ->modalDescription(fn() => $this->selectedTeamId 
                    ? "Verranno scaricati i dati FBref per la squadra " . Team::find($this->selectedTeamId)->name . "."
                    : "⚠️ ATTENZIONE: Verrà avviata la sincronizzazione massiva per TUTTA la stagione " . SeasonHelper::formatYear($this->selectedSeasonYear) . ". L'operazione potrebbe richiedere diversi minuti."
                )
                ->disabled(fn() => !$this->selectedSeasonYear)
                ->action(fn() => $this->runSurgicalSync())
        ];
    }

    public function viewMissingPlayersAction(): Action
    {
        return Action::make('viewMissingPlayers')
            ->slideOver()
            ->modalWidth(MaxWidth::SevenExtraLarge)
            ->modalHeading(function (array $arguments) {
                $season = \App\Models\Season::find($arguments['seasonId']);
                return "Calciatori Mancanti - Stagione " . ($season ? \App\Helpers\SeasonHelper::formatYear($season->season_year) : '');
            })
            ->modalContent(fn (array $arguments) => view('filament.pages.sync.missing-players-panel', [
                'seasonId' => $arguments['seasonId'],
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi');
    }

    public function runSurgicalSync()
    {
        if (!$this->selectedSeasonYear) {
            Notification::make()->title('Errore')->body('Seleziona almeno la stagione.')->danger()->send();
            return;
        }

        $seasonLabel = SeasonHelper::formatYear($this->selectedSeasonYear);
        $teamName = $this->selectedTeamId ? Team::find($this->selectedTeamId)->name : "Tutta la Serie A";

        Notification::make()
            ->title('Sync Avviato')
            ->body("Sincronizzazione in corso per {$teamName} [Stagione {$seasonLabel}]...")
            ->info()
            ->send();

        try {
            if ($this->selectedTeamId) {
                // Sync Team
                Artisan::call('fbref:surgical-team-sync', [
                    'team_id' => $this->selectedTeamId,
                    '--season' => $this->selectedSeasonYear,
                ]);
            } else {
                // Sync Stagione (Massivo)
                // Recuperiamo l'ID della stagione
                $season = \App\Models\Season::where('season_year', $this->selectedSeasonYear)->first();
                if (!$season) throw new \Exception("Stagione non trovata.");
                
                Artisan::call('fbref:surgical-season-sync', [
                    'season_id' => $season->id,
                ]);
            }

            Notification::make()
                ->title('Sync Completato')
                ->body("Dati aggiornati con successo per {$teamName}.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore durante il Sync')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncSingleTeam(int $teamId)
    {
        $this->selectedTeamId = $teamId;
        
        $team = Team::find($teamId);
        if (!$team) return;

        $seasonLabel = SeasonHelper::formatYear($this->selectedSeasonYear);

        Notification::make()
            ->title('Sync Chirurgico Avviato')
            ->body("Sincronizzazione rapida per {$team->name} [Stagione {$seasonLabel}]...")
            ->info()
            ->send();

        try {
            Artisan::call('fbref:surgical-team-sync', [
                'team_id' => $teamId,
                '--season' => $this->selectedSeasonYear,
            ]);

            Notification::make()
                ->title('Sync Completato')
                ->body("Dati aggiornati per {$team->name}.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore Sync Rapido')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function mount(): void
    {
        $this->selectedSeasonYear = SeasonHelper::getCurrentSeason();
    }
}
