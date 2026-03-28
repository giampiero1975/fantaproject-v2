<?php

namespace App\Filament\Widgets;

use App\Models\ProxyService;
use App\Services\ProxyManagerService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProxyStatusWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activeProxies = ProxyService::where('is_active', true)->get();
        
        if ($activeProxies->isEmpty()) {
            return [
                Stat::make('Proxy Status', 'OFFLINE')
                    ->description('Nessun proxy attivo trovato')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger'),
            ];
        }

        $totalRemaining = $activeProxies->sum(fn($p) => max(0, $p->limit_monthly - $p->current_usage));
        $totalLimit = $activeProxies->sum('limit_monthly');
        $totalUsed = $activeProxies->sum('current_usage');
        
        $percentageUsed = $totalLimit > 0 ? round(($totalUsed / $totalLimit) * 100, 1) : 0;

        return [
            Stat::make('Plafond Totale Disponibile', number_format($totalRemaining) . ' cr')
                ->description("Saturazione Prossima: {$percentageUsed}% ({$totalUsed} / {$totalLimit})")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($percentageUsed > 90 ? 'danger' : ($percentageUsed > 70 ? 'warning' : 'success'))
                ->chart([7, 3, 4, 5, 2, 3, 4]),
        ];
    }
}
