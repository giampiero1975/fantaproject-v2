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
    protected static ?string $navigationLabel = '5. Sincronizzazione Rose';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $title           = 'Sincronizzazione Rose Serie A (Step 5)';
    protected static string  $view            = 'filament.pages.sincronizzazione-rose';
    protected static ?string $slug            = 'sincronizzazione-rose';

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            // ── Sincronizza Rose API ──────────────────────────────────────────
            Action::make('syncApiData')
                ->label('Sincronizza Rose API')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sincronizza Rose Serie A')
                ->modalDescription(
                    'Interroga football-data.org per arricchire i player con api_football_data_id, '
                    . 'date_of_birth e posizione. '
                    . 'Stagione auto-rilevata dal DB. '
                    . 'Solo i giocatori non ancora collegati verranno elaborati (usa --force per tutti). '
                    . 'Durata stimata: ~2.5 min. Nessun record verrà cancellato.'
                )
                ->action(function (): void {
                    set_time_limit(300);

                    Notification::make()
                        ->title('⏳ Sincronizzazione avviata...')
                        ->body('Recupero rose da football-data.org. Attendere ~2.5 minuti. La barra di avanzamento si aggiornerà.')
                        ->warning()
                        ->persistent()
                        ->send();

                    try {
                        Artisan::call('players:sync-from-active-teams');

                        $output = Artisan::output();

                        preg_match('/Aggiornati\s*:\s*(\d+)/', $output, $mU);
                        preg_match('/Creati\s*:\s*(\d+)/',     $output, $mC);
                        preg_match('/Orfani\s*:\s*(\d+)/',     $output, $mO);

                        $updated = (int)($mU[1] ?? 0);
                        $created = (int)($mC[1] ?? 0);
                        $orphans = (int)($mO[1] ?? 0);

                        Notification::make()
                            ->title('✅ Sincronizzazione completata!')
                            ->body("Aggiornati: {$updated} | Creati: {$created} | Orfani: {$orphans}")
                            ->success()
                            ->send();

                    } catch (Throwable $e) {
                        Log::error('SincronizzazioneRose::syncApiData — ' . $e->getMessage());
                        Notification::make()
                            ->title('Errore sincronizzazione')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
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
        $currentSeason = DB::table('teams')
            ->where('serie_a_team', 1)
            ->max('season_year');

        // Serie A teams per la stagione corrente
        $activeTeamNames = Team::where('serie_a_team', 1)
            ->where('season_year', $currentSeason)
            ->pluck('short_name')
            ->merge(
                Team::where('serie_a_team', 1)
                    ->where('season_year', $currentSeason)
                    ->pluck('name')
            )
            ->map(fn($n) => strtolower(trim($n)))
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
