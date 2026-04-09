<?php

namespace App\Filament\Pages;

use App\Models\Player;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SincronizzazioneRose extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = '7. Sincronizzazione Rose';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 7;
    protected static ?string $title           = 'Sincronizzazione Rose Serie A (Step 7)';
    protected static string  $view            = 'filament.pages.sincronizzazione-rose';
    protected static ?string $slug            = 'sincronizzazione-rose';

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            // ── Sincronizza Rose API ──────────────────────────────────────────
            // ── Sincronizza Rose API ──────────────────────────────────────────
            Action::make('syncApiData')
                ->label('1. Sync API Football')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sincronizza Rose Serie A (API)')
                ->modalDescription(
                    'Interroga football-data.org per arricchire i player con api_id, proprietá (parent_team) e date_of_birth. '
                    . 'Sincronizza automaticamente le ultime 3 stagioni (Pregresso).'
                )
                ->action(function (): void {
                    set_time_limit(600);
                    Notification::make()->title('⏳ Sync API avviato...')->body('Processo triennale in corso. Monitora la barra progressi.')->warning()->send();

                    try {
                        Artisan::call('players:sync-from-active-teams');
                        Notification::make()->title('✅ Sync API completato!')->success()->send();
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
                    $logs = \App\Models\ImportLog::where('import_type', 'sync_rose_api')
                        ->latest()
                        ->limit(10)
                        ->get();

                    return view('filament.modals.log-analysis', compact('logs'));
                }),
        ];
    }

    // ── Computed Properties per la Blade View ─────────────────────────────────

    /**
     * Coverage summary per le stat inline nella view.
     */
    public function getCoverageProperty(): array
    {
        $total   = Player::count();
        $matched = Player::whereNotNull('api_football_data_id')->count();
        $pct     = $total > 0 ? round($matched / $total * 100, 1) : 0.0;
        return compact('total', 'matched', 'pct');
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
        $currentSeasonModel = \App\Models\Season::where('is_current', true)->first();
        $seasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        // Serie A teams per la stagione corrente
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

        return Player::whereNull('deleted_at')
            ->whereNotNull('fanta_platform_id')
            ->whereNull('api_football_data_id')
            ->select('id', 'name', 'team_name', 'team_id', 'role', 'fanta_platform_id')
            ->orderBy('team_name')
            ->orderBy('name')
            ->get()
            ->map(function ($player) use ($activeTeamNames) {
                $teamLower = strtolower(trim($player->team_name ?? ''));

                if (empty($player->team_id)) {
                    // Tipo B — squadra non riconosciuta in DB (non in Serie A nel dataset)
                    $motivo = '🔴 Tipo B — Squadra non riconosciuta nel DB';
                    $colore = 'red';
                } elseif (!empty($teamLower) && !in_array($teamLower, $activeTeamNames, true)) {
                    // Tipo B — squadra presente nel listone ma retrocessa / non più in A
                    $motivo = '🟡 Tipo B — Squadra Retrocessa / Non più in Serie A';
                    $colore = 'amber';
                } else {
                    // Tipo A — squadra in A, giocatore non matchato (probabile alias / accento)
                    $motivo = '🔵 Tipo A — Probabile Alias (verificare accenti/forma nome)';
                    $colore = 'blue';
                }

                $player->sospetto_motivo = $motivo;
                $player->motivo_colore   = $colore;
                return $player;
            });
    }
}
