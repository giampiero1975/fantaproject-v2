<?php

namespace App\Filament\Widgets;

use App\Models\Player;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FbrefCoverageStats extends BaseWidget
{
    protected static ?int    $sort            = 15;
    protected static ?string $pollingInterval = null;

    // Full-width: occupa tutta la riga della dashboard (Riga 3)
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Totale giocatori unici nelle rose di Serie A (con fanta_platform_id o non soft-deleted)
        $totalTarget = DB::select("
            select count(DISTINCT p.id) as cnt
            from player_season_roster psr
            inner join players p on p.id = psr.player_id
            inner join team_season ts on psr.team_id = ts.team_id and psr.season_id = ts.season_id
            where ts.league_id = 1 
              and (p.deleted_at is null or p.fanta_platform_id is not null)
        ")[0]->cnt;

        $mapped = DB::select("
            select count(DISTINCT p.id) as cnt
            from player_season_roster psr
            inner join players p on p.id = psr.player_id
            inner join team_season ts on psr.team_id = ts.team_id and psr.season_id = ts.season_id
            where ts.league_id = 1 
              and (p.deleted_at is null or p.fanta_platform_id is not null)
              and p.fbref_id is not null
        ")[0]->cnt;

        $missing = $totalTarget - $mapped;
        $pct = $totalTarget > 0 ? round($mapped / $totalTarget * 100, 1) : 0.0;

        // Semaforo Step 8 — FBref
        $s8Icon  = $pct >= 90 ? '🟢' : ($pct >= 50 ? '🟡' : '🔴');
        $s8Color = $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
        
        $s8Desc = $totalTarget === 0 
            ? 'Nessun giocatore in roster'
            : "Copertura FBref globale: {$pct}%";

        // Recupero ultimo sync eseguito
        $lastSync = DB::table('import_logs')
            ->whereIn('import_type', ['fbref_surgical_season_sync', 'fbref_surgical_team_sync'])
            ->latest()
            ->first();

        if ($lastSync) {
            $time = \Carbon\Carbon::parse($lastSync->created_at)->diffForHumans();
            $s8Desc .= " (Ultimo sync: {$time})";
        }

        // Semaforo Mancanti
        $missIcon  = $missing === 0 ? '🟢' : ($missing <= 50 ? '🟡' : '🔴');
        $missColor = $missing === 0 ? 'success' : ($missing <= 50 ? 'warning' : 'danger');
        
        $missDesc = match(true) {
            $missing === 0 => '✅ Step 8 certificato — copertura completa',
            $missing <= 50 => "⚠️ {$missing} orfani FBref — controllo manuale",
            default        => "🔴 {$missing} orfani FBref — copertura insufficiente",
        };

        return [
            Stat::make("{$s8Icon} Step 8 — FBref", "{$mapped} / {$totalTarget}")
                ->description($s8Desc)
                ->descriptionIcon('heroicon-m-link')
                ->color($s8Color),

            Stat::make("{$missIcon} Mancanti FBref", number_format($missing))
                ->description($missDesc)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($missColor),
        ];
    }
}
