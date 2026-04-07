<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Widget Riga 1 — Setup Iniziale (Step 1 · Step 2 · Step 3)
 *
 * Target season = anno corrente - 1 (la stagione "in corso" usa lo start-year)
 * Esempio: siamo nel 2026 → stagione 2025/26 → season_year = 2025
 *
 * Slot: full-width (columnSpan = full), sort = 1
 */
class SetupStepsOverview extends BaseWidget
{
    protected static ?int    $sort            = 1;
    protected static ?string $pollingInterval = null;

    // Full-width: occupa tutta la riga della dashboard
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // ── Calcolo stagione target ────────────────────────────────────────────
        $currentSeasonModel = \App\Models\Season::where('is_current', true)->first();
        $targetSeason = $currentSeasonModel ? $currentSeasonModel->season_year : ((int) date('Y') - 1);
        $seasonLabel  = $targetSeason . '/' . substr((string)($targetSeason + 1), 2); // "2025/26"
        $seasonId = $currentSeasonModel ? $currentSeasonModel->id : 0;

        // Verifica quante squadre con season_year = $targetSeason esistono
        $total     = 20; // Atteso: 20 squadre Serie A
        
        $withApiId = Team::whereNotNull('api_id')->count();
        $withTier  = Team::whereNotNull('tier_globale')->count();
        
        // Configurato = squadre attive per questa stagione
        $configured = \App\Models\TeamSeason::where('season_id', $seasonId)
                         ->where('is_active', true)
                         ->count();

        // Se non c'è il TeamSeason usiamo le API id come fallback
        if ($configured === 0 && $currentSeasonModel) {
             $configured = $withApiId;
        }

        // Distribuzione tier quando presente
        $tierDist = Team::whereNotNull('tier_globale')
            ->selectRaw('tier_globale, count(*) as cnt')
            ->groupBy('tier_globale')
            ->orderBy('tier_globale')
            ->pluck('cnt', 'tier_globale')
            ->toArray();
        $tierLabel = count($tierDist)
            ? collect($tierDist)->map(fn($c, $t) => "T{$t}:{$c}")->implode(' · ')
            : 'nessun tier calcolato';

        // ── Semafori ──────────────────────────────────────────────────────────

        // Step 1 — Stagione: 🔴 se 0 squadre, 🟢 se almeno 1 squadra configurata
        $s1Color = $configured > 0 ? 'success' : 'danger';
        $s1Icon  = $configured > 0 ? '🟢' : '🔴';
        $s1Desc  = $configured > 0
            ? "{$configured} squadre già caricate per la stagione"
            : 'Nessuna squadra — eseguire Setup Step 1 (ImportTeams)';

        // Step 2 — API ID: 🔴 se 0, 🟡 se parziale, 🟢 se 20/20
        $s2Color  = $withApiId === $total ? 'success' : ($withApiId > 0 ? 'warning' : 'danger');
        $s2Icon   = $withApiId === $total ? '🟢' : ($withApiId > 0 ? '🟡' : '🔴');
        $missing  = $total - $withApiId;
        $s2Desc   = $withApiId === 0
            ? 'Nessuna squadra con api_football_data_id — Step 2 mancante'
            : ($withApiId < $total
                ? "{$withApiId}/{$total} — {$missing} squadre senza ID API"
                : "Tutte le squadre sono collegate all'API");

        // Step 3 — Tier: 🔴 se 0, 🟡 se parziale, 🟢 se 20/20
        $s3Color = $withTier === $total ? 'success' : ($withTier > 0 ? 'warning' : 'danger');
        $s3Icon  = $withTier === $total ? '🟢' : ($withTier > 0 ? '🟡' : '🔴');
        $s3Desc  = $withTier === 0
            ? 'Tier non calcolati — eseguire Step 3 (CalcolaTier)'
            : "Distribuzione: {$tierLabel}";

        return [
            Stat::make("{$s1Icon} Step 1 — Stagione", $seasonLabel)
                ->description($s1Desc)
                ->descriptionIcon($configured > 0 ? 'heroicon-m-calendar' : 'heroicon-m-exclamation-circle')
                ->color($s1Color),

            Stat::make("{$s2Icon} Step 2 — Squadre API", "{$withApiId} / {$total}")
                ->description($s2Desc)
                ->descriptionIcon($withApiId === $total ? 'heroicon-m-check-circle' : 'heroicon-m-building-office-2')
                ->color($s2Color),

            Stat::make("{$s3Icon} Step 3 — Tier", "{$withTier} / {$total}")
                ->description($s3Desc)
                ->descriptionIcon($withTier === $total ? 'heroicon-m-check-circle' : 'heroicon-m-chart-bar')
                ->color($s3Color),
        ];
    }
}
