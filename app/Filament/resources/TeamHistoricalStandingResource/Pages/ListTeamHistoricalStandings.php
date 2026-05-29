<?php
namespace App\Filament\Resources\TeamHistoricalStandingResource\Pages;

use App\Filament\Resources\TeamHistoricalStandingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamHistoricalStandings extends ListRecords
{
    protected static string $resource = TeamHistoricalStandingResource::class;

    protected function getHeaderWidgets(): array
    {
        return static::$resource::getWidgets();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('check_coverage')
                ->label('Verifica Copertura')
                ->icon('heroicon-o-table-cells')
                ->color('info')
                ->url(fn () => static::$resource::getUrl('coverage')),
        ];
    }
}