<?php

namespace App\Filament\Widgets;

use App\Models\Player;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SyncCoverageStats extends BaseWidget
{
    protected static ?int    $sort            = 10;
    protected static ?string $pollingInterval = null;

    // Full-width: occupa tutta la riga della dashboard (Riga 2)
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $total      = Player::count();
        $withFanta  = Player::whereNotNull('fanta_platform_id')->count();
        $matched    = Player::whereNotNull('api_football_data_id')->count();
        $orphans    = Player::whereNotNull('fanta_platform_id')->whereNull('api_football_data_id')->count();
        $newFromApi = Player::whereNull('fanta_platform_id')->whereNotNull('api_football_data_id')->count();
        $pct        = $total > 0 ? round($matched / $total * 100, 1) : 0.0;

        // Semaforo Step 4
        $s4Icon  = $withFanta >= 500 ? '🟢' : ($withFanta > 0 ? '🟡' : '🔴');
        $s4Color = $withFanta >= 500 ? 'success' : ($withFanta > 0 ? 'warning' : 'danger');
        $s4Desc  = $withFanta === 0
            ? 'Listone non ancora importato — eseguire Step 4'
            : "Importati da Fantagazzetta (fanta_platform_id)";

        // Semaforo Step 5 — Copertura
        $s5Icon  = $pct >= 90 ? '🟢' : ($pct >= 50 ? '🟡' : '🔴');
        $s5Color = $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
        $s5Desc  = $total === 0
            ? 'Nessun giocatore — importare il listone prima'
            : "{$pct}% con api_football_data_id collegato";

        // Semaforo Orfani
        $orpIcon  = $orphans === 0 ? '🟢' : ($orphans <= 50 ? '🟡' : '🔴');
        $orpColor = $orphans === 0 ? 'success' : ($orphans <= 50 ? 'warning' : 'danger');
        $orpDesc  = match(true) {
            $orphans === 0 => '✅ Step 5 certificato — copertura completa',
            $orphans <= 50 => "⚠️ {$orphans} orfani — controllo manuale consigliato",
            default        => "🔴 {$orphans} orfani — eseguire Sync Rose API",
        };

        return [
            Stat::make("{$s4Icon} Step 4 — Listone", number_format($withFanta))
                ->description($s4Desc)
                ->descriptionIcon('heroicon-m-users')
                ->color($s4Color),

            Stat::make("{$s5Icon} Step 5 — Copertura API", $total > 0 ? "{$matched}/{$total}" : '—')
                ->description($s5Desc)
                ->descriptionIcon('heroicon-m-link')
                ->color($s5Color),

            Stat::make("{$orpIcon} Orfani Listone", number_format($orphans))
                ->description($orpDesc)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($orpColor),

            Stat::make('➕ L4 — Creati da API', number_format($newFromApi))
                ->description($newFromApi > 0 ? 'In rosa API, non nel listone (riserve)' : 'Nessun giocatore creato da API')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color($newFromApi > 0 ? 'info' : 'gray'),
        ];
    }
}
