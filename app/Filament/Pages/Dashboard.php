<?php

namespace App\Filament\Pages;

use App\Models\ImportLog;
use App\Models\Player;
use App\Models\Team;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;

class Dashboard extends BaseDashboard
{
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
        $currentYear  = (int) date('Y');
        $targetSeason = $currentYear - 1;  // 2025 = stagione 2025/26
        $seasonLabel  = $targetSeason . '/' . substr((string)$currentYear, 2);

        // ── Step 1-3: Teams ───────────────────────────────────────────────────
        $teamTotal        = Team::where('serie_a_team', 1)->where('season_year', $targetSeason)->count();
        $teamWithApi      = Team::where('serie_a_team', 1)->where('season_year', $targetSeason)->whereNotNull('api_football_data_id')->count();
        $teamWithShortName = Team::where('serie_a_team', 1)->where('season_year', $targetSeason)->whereNotNull('short_name')->count();
        $teamWithTier     = Team::where('serie_a_team', 1)->where('season_year', $targetSeason)->whereNotNull('tier')->count();

        $tierDist = Team::where('serie_a_team', 1)->where('season_year', $targetSeason)
            ->whereNotNull('tier')->selectRaw('tier, count(*) as cnt')
            ->groupBy('tier')->orderBy('tier')
            ->pluck('cnt', 'tier')->toArray();

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

        // ── Stato per step (propedeutica): Step 1 unificato DB+API ──────────
        $step1Ok = $teamTotal >= 20 && $teamWithApi >= 20;  // ENTRAMBI richiesti
        $step2Ok = $step1Ok;                                 // alias: step2 fuso in step1
        $step3Ok = $teamWithTier >= 20;
        $step4Ok = $playerFanta >= 400;
        $step5Ok = $pct >= 90;

        return compact(
            'targetSeason', 'seasonLabel', 'currentYear',
            'teamTotal', 'teamWithApi', 'teamWithShortName', 'teamWithTier', 'tierDist',
            'playerTotal', 'playerFanta', 'playerApi', 'playerOrphan', 'pct',
            'lastListone', 'lastSync',
            'step1Ok', 'step2Ok', 'step3Ok', 'step4Ok', 'step5Ok'
        );
    }
}
