<?php

namespace App\Filament\Pages;

use App\Helpers\SeasonHelper;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\ImportLog;
use App\Services\SeasonMonitorService;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = "heroicon-o-home";
    protected static ?string $navigationLabel = "Dashboard";
    protected static ?string $title           = "FantaProject v2";
    protected static ?int    $navigationSort  = -1;

    protected static string $view = 'filament.pages.dashboard';

    protected function getViewData(): array
    {
        $monitor      = app(SeasonMonitorService::class);
        $seasonStatus = $monitor->getStatus();
        
        $seasonStatusLabel = $seasonStatus['label'] ?? 'N/A';
        $proxyStatus       = app(\App\Services\ProxyManagerService::class)->getProxyStatus();

        // ── Dati Stagione Corrente ──────────────────────────────────────────
        $currentSeasonModel = Season::where('is_current', true)->first();
        $lookbackStatus     = $monitor->getHistoricalLookback();

        $teamWithFbref = 0;
        $fbrefPct = 0;

        if ($currentSeasonModel) {
            $teamTotal = $currentSeasonModel->teams()->count();
            $teamWithApi   = $currentSeasonModel->teams()->whereNotNull('api_id')->count();
            $apiMissingCount  = $teamTotal - $teamWithApi;
            
            // Check anagrafica fbref
            $fbrefIncomplete = $currentSeasonModel->teams()
                ->where(function($q) {
                    $q->whereNull('fbref_slug')->orWhere('fbref_slug', '');
                })->exists();

            $teamWithFbref = $currentSeasonModel->teams()
                ->whereNotNull('fbref_id')
                ->where('fbref_id', '!=', '')
                ->whereNotNull('fbref_slug')
                ->where('fbref_slug', '!=', '')
                ->count();
                
            $fbrefPct = $teamTotal > 0 ? round(($teamWithFbref / $teamTotal) * 100) : 0;
        }

        // ── Dati Storico ────────────────────────────────────────────────────
        // Calcoliamo le stagioni incluse nel lookback (es. ultimi 4 anni)
        $lookbackYears = collect($lookbackStatus['years'] ?? [])
            ->pluck('year')
            ->toArray();
        // Conta i record presenti nella tabella team_historical_standings per le stagioni di lookback
        $standingCount = DB::table('team_historical_standings')
            ->whereIn('season_year', $lookbackYears)
            ->count();
        // Il target è il numero di squadre per stagione moltiplicato per il numero di stagioni di lookback
        $standingTarget = count($lookbackYears) * 20;

        // ── Dati Tier ───────────────────────────────────────────────────────
        $teamWithTier = Team::whereNotNull('tier_globale')->count();
        $tierDist = Team::whereNotNull('tier_globale')
            ->select('tier_globale as tier', DB::raw('count(*) as total'))
            ->groupBy('tier_globale')->pluck('total', 'tier')->toArray();

        // ── Dati Listone ────────────────────────────────────────────────────
        $playerTotal = Player::count();
        $playerFanta = Player::whereNotNull('fanta_platform_id')->count();
        $playerApi   = Player::whereNotNull('api_football_data_id')->count();
        $playerOrphan = Player::whereNull('parent_team_id')->count();

        $lastListone = ImportLog::where('import_type', 'listone_gazzetta')
            ->where('status', 'successo')
            ->latest()->first();

        // ── Dati Sync ───────────────────────────────────────────────────────
        $pct = ($playerFanta > 0) ? round(($playerApi / $playerFanta) * 100, 1) : 0;
        $lastSync = ImportLog::where('import_type', 'sync_rose_api')
            ->where('status', 'successo')
            ->latest()->first();

        // ── Step Logic ──────────────────────────────────────────────────────
        $step1Ok = $currentSeasonModel && $lookbackStatus['is_ready'];
        $step2Ok = $teamTotal >= 20 && $apiMissingCount === 0;
        $step3Ok = $standingCount >= $standingTarget;
        $step4Ok = $teamWithTier >= 20;
        $step5Ok = $playerFanta >= 400;

        return compact(
            'seasonStatus', 'seasonStatusLabel', 'proxyStatus',
            'currentSeasonModel', 'lookbackStatus',
            'teamTotal', 'teamWithApi', 'apiMissingCount', 'fbrefIncomplete',
            'teamWithFbref', 'fbrefPct',
            'standingCount', 'standingTarget',
            'teamWithTier', 'tierDist',
            'playerTotal', 'playerFanta', 'playerApi', 'playerOrphan',
            'lastListone', 'lastSync', 'pct',
            'step1Ok', 'step2Ok', 'step3Ok', 'step4Ok', 'step5Ok'
        );
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
