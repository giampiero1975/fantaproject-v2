<?php

namespace App\Filament\Pages;

use App\Models\ImportLog;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SincronizzazioneRose extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = '7. Sincronizzazione Rose';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 7;
    protected static ?string $title           = 'Sincronizzazione Rose Serie A (Step 7)';
    protected static string  $view            = 'filament.pages.sincronizzazione-rose';
    protected static ?string $slug            = 'sincronizzazione-rose';

    public ?int $selectedSeasonId = null;
    public $lookbackStatus        = null;

    public function mount(): void
    {
        if (!$this->selectedSeasonId) {
            $this->selectedSeasonId = Season::where('is_current', true)->first()?->id ?? Season::latest('season_year')->first()?->id;
        }
        $this->computeLookback();
    }

    public function computeLookback(): void
    {
        $status = app(\App\Services\SeasonMonitorService::class)->getHistoricalLookback();
        
        // Arricchiamo ogni anno con le statistiche di copertura
        if (isset($status['years'])) {
            foreach ($status['years'] as &$yearData) {
                // Cerchiamo l'ID della stagione per l'anno di riferimento
                $season = Season::where('season_year', $yearData['year'])->first();
                if (!$season) continue;

                $sId = $season->id;
                $total   = PlayerSeasonRoster::where('season_id', $sId)->count();
                $matched = PlayerSeasonRoster::where('season_id', $sId)
                    ->whereHas('player', fn($q) => $q->whereNotNull('api_football_data_id'))
                    ->count();
                    
                $l4Count = PlayerSeasonRoster::where('season_id', $sId)
                    ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
                    ->count();

                $isSynced = \App\Models\ImportLog::where('import_type', 'sync_rose_api_historical')
                    ->where('season_id', $sId)
                    ->where('status', 'successo')
                    ->exists();

                $yearData['stats'] = [
                    'total'     => $total,
                    'matched'   => $matched,
                    'pct'       => $total > 0 ? round($matched / $total * 100, 1) : 0.0,
                    'l4'        => $l4Count,
                    'is_synced' => $isSynced,
                ];
            }
        }

        $this->lookbackStatus = $status;
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            // ── Sincronizza Rose API ──────────────────────────────────────────
            // ── Sincronizza Rose API ──────────────────────────────────────────
            Action::make('syncHistoricalSeason')
                ->label('1. Sincronizzazione Storica')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(fn() => "Sincronizza Rose " . Season::find($this->selectedSeasonId)?->season_year)
                ->modalDescription(
                    'Interroga l\'API per la stagione selezionata. '
                    . 'Questo aggiorner api_id e propriet storiche nel roster.'
                )
                ->action(function (): void {
                    $year = Season::find($this->selectedSeasonId)?->season_year;
                    if (!$year) return;

                    set_time_limit(600);
                    Notification::make()->title("⏳ Sync {$year} avviato...")
                        ->body('Processo stagionale in corso. Monitora la barra progressi.')
                        ->warning()->send();

                    try {
                        Artisan::call('players:historical-sync', ['--season' => $year]);
                        $this->computeLookback(); // Rinfresca il widget dopo la sync
                        
                        // Recuperiamo l'esito dal log appena scritto
                        $lastLog = \App\Models\ImportLog::where('import_type', 'sync_rose_api_historical')
                            ->where('season_id', $this->selectedSeasonId)
                            ->latest()
                            ->first();

                        if ($lastLog && ($lastLog->status === 'errore' || $lastLog->status === 'fallito')) {
                            Notification::make()
                                ->title("❌ Sync {$year} Fallito")
                                ->body($lastLog->details)
                                ->danger()
                                ->persistent()
                                ->send();
                        } elseif ($lastLog && $lastLog->status === 'parziale') {
                            Notification::make()
                                ->title("⚠️ Sync {$year} Parziale")
                                ->body($lastLog->details)
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("✅ Sync {$year} completato!")
                                ->success()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()->title('Errore API')->body($e->getMessage())->danger()->send();
                    }
                }),

            // ── Sincronizza FBref IDs ──────────────────────────────────────────
            Action::make('syncFbrefData')
                ->label('2. Sync FBref IDs')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Ricerca Automatica ID FBref')
                ->modalDescription('Cerca gli URL e gli ID FBref mancanti tramite ricerca semantica dei nomi. Consuma crediti proxy.')
                ->action(function (): void {
                    set_time_limit(600);
                    Notification::make()->title('⏳ Ricerca FBref avviata...')->body('Ricerca in corso per i giocatori mancanti di URL.')->info()->send();

                    try {
                        Artisan::call('fbref:update-player-fbref-urls', ['--all' => true]);
                        Notification::make()->title('✅ Ricerca FBref completata!')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Errore FBref')->body($e->getMessage())->danger()->send();
                    }
                }),

            // ── Storico Sincronizzazioni (da import_logs) ───────────────────────
            Action::make('analyzeLog')
                ->label('Storico Sync')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Cronologia Sincronizzazioni Rose API')
                ->modalSubmitActionLabel('Chiudi')
                ->action(fn () => null)
                ->modalContent(function () {
                    $logs = \App\Models\ImportLog::with('season')
                        ->whereIn('import_type', ['sync_rose_api', 'sync_rose_api_historical'])
                        ->latest()
                        ->limit(10)
                        ->get();

                    return view('filament.modals.log-analysis', compact('logs'));
                }),
        ];
    }

    // ── Configuration Tabella (HasTable) ─────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PlayerSeasonRoster::query()
                    ->with(['player', 'team', 'season', 'parentTeam'])
                    ->where('season_id', $this->selectedSeasonId)
                    ->whereHas('player', fn($q) => $q->whereNotNull('api_football_data_id'))
            )
            ->columns([
                ImageColumn::make('team.logo_url')
                    ->label('Logo')
                    ->circular(),
                TextColumn::make('team.short_name')
                    ->label('Squadra')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('player.name')
                    ->label('Giocatore')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => 
                        $record->parent_team_id && $record->parent_team_id !== $record->team_id
                        ? "🛡️ Proprietà: " . ($record->parentTeam?->short_name ?? $record->parentTeam?->name)
                        : "ID: {$record->player_id}"
                    ),
                TextColumn::make('player.api_football_data_id')
                    ->label('API ID')
                    ->copyable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'P' => 'warning',
                        'D' => 'success',
                        'C' => 'info',
                        'A' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Sincronizza la stagione per vedere i risultati');
    }

    public function getStats(): array
    {
        $coverage = $this->coverage;
        return [
            [
                'label' => 'Giocatori in Roster',
                'value' => $coverage['total'],
                'color' => 'gray',
            ],
            [
                'label' => 'Matchati API',
                'value' => $coverage['matched'],
                'color' => 'success',
            ],
            [
                'label' => '% Copertura API',
                'value' => $coverage['pct'] . '%',
                'color' => $coverage['pct'] > 90 ? 'success' : ($coverage['pct'] > 70 ? 'warning' : 'danger'),
            ],
            [
                'label' => 'Nuovi Creati (L4)',
                'value' => $coverage['l4Count'],
                'color' => 'amber',
            ],
        ];
    }

    // ── Computed Properties per la Blade View ─────────────────────────────────

    /**
     * Coverage summary per le stat inline nella view.
     */
    public function getCoverageProperty(): array
    {
        $seasonId = $this->selectedSeasonId;

        $total   = PlayerSeasonRoster::where('season_id', $seasonId)->count();
        $matched = PlayerSeasonRoster::where('season_id', $seasonId)
            ->whereHas('player', fn($q) => $q->whereNotNull('api_football_data_id'))
            ->count();
            
        $l4Count = PlayerSeasonRoster::where('season_id', $seasonId)
            ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
            ->count();

        $pct     = $total > 0 ? round($matched / $total * 100, 1) : 0.0;
        return compact('total', 'matched', 'l4Count', 'pct');
    }

    /**
     * Legge il progresso real-time dalla Cache (scritto dal comando Artisan).
     */
    public function getSyncProgressProperty(): array
    {
        return Cache::get('sync_rose_progress', [
            'running' => false,
            'percent' => 0,
            'label'   => 'Nessuna sincronizzazione in corso.',
            'team'    => '',
            'done'    => false,
            'log_id'  => null,
        ]);
    }

    /**
     * Lista degli orfani con diagnostica del sospetto motivo.
     * Logica:
     *  - Se la squadra non è più in Serie A (season_year corrente) → "Squadra Retrocessa/Inattiva"
     *  - Se la squadra è in A ma il giocatore non è matchato → "Mancato Match — Verificare Alias"
     *  - Se team_id è NULL → "Squadra non riconosciuta in DB"
     */
    public function getOrphansProperty(): \Illuminate\Support\Collection
    {
        $seasonId = $this->selectedSeasonId;
        $seasonModel = Season::find($seasonId);
        
        if (!$seasonModel) return collect();

        // Serie A teams per la stagione selezionata
        $activeTeams = Team::whereHas('teamSeasons', function ($q) use ($seasonId) {
            $q->where('season_id', $seasonId)->where('is_active', true);
        })->get();

        if ($activeTeams->isEmpty()) {
            $activeTeams = Team::whereNotNull('api_id')->get();
        }

        $activeTeamNames = $activeTeams->pluck('short_name')
            ->merge($activeTeams->pluck('name'))
            ->map(fn($n) => strtolower(trim((string)$n)))
            ->unique()
            ->values()
            ->toArray();

        $hasSynced = ImportLog::where('import_type', 'sync_rose_api_historical')
            ->where('season_id', $seasonId)
            ->where('status', 'successo')
            ->exists();

        return Player::with(['rosters' => fn($q) => $q->where('season_id', $seasonId)])
            ->whereHas('rosters', fn($q) => $q->where('season_id', $seasonId))
            ->whereNull('deleted_at')
            ->whereNotNull('fanta_platform_id')
            ->whereNull('api_football_data_id')
            ->get()
            ->sortBy('team_name')
            ->map(function ($player) use ($activeTeamNames, $hasSynced) {
                $teamLower = strtolower(trim($player->team_name ?? ''));

                if (empty($player->team_id)) {
                    $motivo = '🔴 Tipo B — Squadra non riconosciuta nel DB';
                    $colore = 'red';
                } elseif (!empty($teamLower) && !in_array($teamLower, $activeTeamNames, true)) {
                    $motivo = '🟡 Tipo B — Squadra Retrocessa / Non più in Serie A';
                    $colore = 'amber';
                } else {
                    if (!$hasSynced) {
                        $motivo = '⚪ Stagione non ancora sincronizzata';
                        $colore = 'gray';
                    } else {
                        $motivo = '🔵 Tipo A — Probabile Alias (verificare accenti/forma nome)';
                        $colore = 'blue';
                    }
                }

                $player->sospetto_motivo = $motivo;
                $player->motivo_colore   = $colore;
                return $player;
            });
    }
}
