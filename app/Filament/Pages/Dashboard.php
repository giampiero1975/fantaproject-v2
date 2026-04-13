<?php

namespace App\Filament\Pages;

use App\Helpers\SeasonHelper;
use App\Models\Season;
use App\Models\TeamSeason;
use App\Models\ImportLog;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamHistoricalStanding;
use App\Services\TeamFbrefAlignmentService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;

class Dashboard extends BaseDashboard
{
    /**
     * Conta le squadre di Serie A 2025 che non hanno ancora l'URL FBref
     * Utilizzato nella view Blade
     */
    public function getMissingFbrefTeamsCount(): int
    {
        $currentSeasonModel = Season::where('is_current', true)->first();
        if (!$currentSeasonModel) return 0;

        return TeamSeason::where('season_id', $currentSeasonModel->id)
            ->whereHas('team', fn($q) => $q->whereNull('fbref_id'))
            ->count();
    }

    protected static ?string $navigationIcon  = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title           = 'FantaProject v2';
    protected static ?int    $navigationSort  = -1;

    // Nessun widget Filament — usiamo una view Blade custom
    public function getWidgets(): array { return []; }
    public function getColumns(): int|array { return 1; }

    protected static string $view = 'filament.pages.dashboard';

    /**
     * Calcola tutti i dati per i 5 step e li passa alla view.
     */
    public function getViewData(): array
    {
        $targetSeason = SeasonHelper::getCurrentSeason();
        $seasonLabel  = $targetSeason . '/' . substr((string)($targetSeason + 1), 2);
        $currentYear  = Carbon::now()->year;

        // ── Step 1: Teams (v8.0 Normalizzata via Pivot) ─────────────────────
        $currentSeasonModel = Season::where('season_year', $targetSeason)->first();
        $seasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        $teamIds = TeamSeason::where('season_id', $seasonId)
            ->pluck('team_id');

        $teamTotal         = count($teamIds);
        $teamWithApi       = Team::whereIn('id', $teamIds)->whereNotNull('api_id')->count();
        $teamWithShortName = Team::whereIn('id', $teamIds)->whereNotNull('short_name')->count();
        $teamWithFbref     = Team::whereIn('id', $teamIds)->whereNotNull('fbref_id')->count();
        $fbrefRatio        = "{$teamWithFbref} / {$teamTotal}";

        // ── Step 2: Historical Standings (Lookback da PREDICTIVE_LOOKBACK_YEARS) ───
        $lookback      = (int) config('projection_settings.tier_calculation.lookback_seasons', 4);
        $historyYears  = array_keys(SeasonHelper::getCompletedLookbackSeasons($lookback));
        $standingCount = TeamHistoricalStanding::whereIn('team_id', $teamIds)
            ->whereIn('season_year', $historyYears)
            ->count();
        $standingTarget = count($teamIds) * $lookback; // dinamico: squadre attive * anni lookback

        // ── Step 3: Tiers ────────────────────────────────────────────────────
        $teamWithTier   = Team::whereIn('id', $teamIds)->whereNotNull('tier_globale')->count();

        $tierDist = Team::whereIn('id', $teamIds)
            ->whereNotNull('tier_globale')->selectRaw('tier_globale, count(*) as cnt')
            ->groupBy('tier_globale')->orderBy('tier_globale')
            ->pluck('cnt', 'tier_globale')->toArray();

        // ── Step 4: Listone ───────────────────────────────────────────────────
        $playerTotal   = Player::count();
        $playerFanta   = Player::whereNotNull('fanta_platform_id')->count();
        $lastListone   = ImportLog::where('import_type', 'main_roster')
            ->where('status', 'like', '%success%')
            ->orWhere('status', 'successo')
            ->latest()->first();

        // ── Step 5: Sync API ─────────────────────────────────────────────────
        $playerApi    = Player::whereNotNull('api_football_data_id')->count();
        $playerOrphan = Player::whereNotNull('fanta_platform_id')->whereNull('api_football_data_id')->count();
        $pct          = $playerTotal > 0 ? round($playerApi / $playerTotal * 100, 1) : 0.0;
        $lastSync     = ImportLog::where('import_type', 'sync_rose_api')
            ->where('status', 'successo')
            ->latest()->first();

        // ── Stato per step (propedeutica) ───────────────────────────────────
        $fbrefMissingCount = Team::whereIn('id', $teamIds)->whereNull('fbref_id')->count();
        $fbrefMappedCount  = Team::whereIn('id', $teamIds)->whereNotNull('fbref_id')->count();
        $apiMissingCount   = Team::whereIn('id', $teamIds)->whereNull('api_id')->count();
        $apiMappedCount    = Team::whereIn('id', $teamIds)->whereNotNull('api_id')->count();

        $missingFbrefCount = Team::whereIn('id', $teamIds)
            ->where(function($query) {
                $query->whereNull('fbref_url')->orWhere('fbref_url', '');
            })->count();

        $fbrefIncomplete = $fbrefMissingCount > 0;

        // ── Step 2: History ──────────────────────────────────────────────────
        $historyStats = TeamHistoricalStanding::select('season_year', DB::raw('count(*) as total'))
            ->whereIn('season_year', $historyYears)
            ->groupBy('season_year')
            ->get()
            ->pluck('total', 'season_year')
            ->toArray();
        
        $coveringSeasonsCount = count($historyStats);
        $totalHistoricalRecords = array_sum($historyStats);
        $missingHistoryYears = array_diff($historyYears, array_keys($historyStats));

        $step1Ok = $teamTotal >= 20 && $teamWithApi >= 20 && $missingFbrefCount === 0 && !$fbrefIncomplete;
        $step2Ok = $step1Ok && $coveringSeasonsCount >= $lookback && $standingCount >= (int)($standingTarget * 0.8); // ok se ≥80% del target (promozioni/retrocessioni ammesse)
        $step3Ok = $step2Ok && $teamWithTier >= 20;
        $step4Ok = $step3Ok && $playerFanta >= 400;
        $step5Ok = $step4Ok && $pct >= 90;

        // ── Step 6: Season Monitor ──────────────────────────────────────────
        $monitorService = app(\App\Services\SeasonMonitorService::class);
        $seasonStatus = $monitorService->getStatus();
        $seasonStatusLabel = $seasonStatus['label'] ?? 'N/A';

        // ── Step 7: Football Hub Counter Logic (v8.0 Normalizzata) ─────────
        $currentSeasonModel = Season::where('is_current', true)->first();
        $activeSeasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        // ── Step 1 (Nuovo): Gestione Stagioni (Lookback 4 anni) ────────────
        $lookbackStatus = $monitorService->getHistoricalLookback();

        // ── Step 0 (Nuovo): Proxy Status ────────────────────────────────────
        $activeProxies = \App\Models\ProxyService::where('is_active', true)->get();
        $totalRemaining = $activeProxies->sum(fn($p) => max(0, $p->limit_monthly - $p->current_usage));
        $totalLimit = $activeProxies->sum('limit_monthly');
        $percentageUsed = $totalLimit > 0 ? round(($activeProxies->sum('current_usage') / $totalLimit) * 100, 1) : 0;
        $proxyStatus = [
            'total_remaining' => $totalRemaining,
            'percentage_used' => $percentageUsed,
            'is_active' => $activeProxies->isNotEmpty()
        ];

        // Squadre attive nella stagione corrente (via pivot)
        $teamsActiveCount = TeamSeason::where('season_id', $activeSeasonId)
            ->count();

        $teamsUniqueCount = Team::count(); // Master records unici
        $teamsTotalCount  = TeamSeason::count(); // Tutti gli snapshot storici


        return compact(
            'targetSeason', 'seasonLabel', 'currentYear', 'lookback',
            'teamTotal', 'teamWithApi', 'teamWithShortName', 'standingCount', 'standingTarget',
            'teamWithTier', 'tierDist',
            'playerTotal', 'playerFanta', 'playerApi', 'playerOrphan', 'pct',
            'lastListone', 'lastSync',
            'step1Ok', 'step2Ok', 'step3Ok', 'step4Ok', 'step5Ok',
            'missingFbrefCount', 'fbrefIncomplete',
            'historyYears', 'coveringSeasonsCount', 'totalHistoricalRecords', 'missingHistoryYears',
            'seasonStatus', 'seasonStatusLabel', 'currentSeasonModel',
            'teamsActiveCount', 'teamsUniqueCount', 'teamsTotalCount',
            'apiMissingCount', 'apiMappedCount', 
            'fbrefMissingCount', 'fbrefMappedCount',
            'lookbackStatus', 'proxyStatus'
        );
    }

    public function triggerHistoryScraping()
    {
        try {
            $service = app(\App\Services\LeagueHistoryScraperService::class);
            $result = $service->scrapeHistory();

            if ($result['status'] === 'success') {
                Notification::make()
                    ->title('Storico Importato')
                    ->body("Creati: {$result['stats']['created']}, Aggiornati: {$result['stats']['updated']}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Errore Importazione')
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
    }

    public function triggerSeasonSync()
    {
        try {
            $targetSeason = SeasonHelper::getCurrentSeason();
            \Illuminate\Support\Facades\Artisan::call('football:sync-serie-a', [
                'season_year' => $targetSeason
            ]);
            
            Notification::make()
                ->title('Sincronizzazione Completata')
                ->body('Tutti i record snapshot e le stagioni sono stati aggiornati.')
                ->success()
                ->send();
                
            return redirect(\App\Filament\Pages\Dashboard::getUrl());
        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore Sincronizzazione')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
