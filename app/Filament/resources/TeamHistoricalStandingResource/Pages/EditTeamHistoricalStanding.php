<?php

namespace App\Filament\Resources\TeamHistoricalStandingResource\Pages;

use App\Filament\Resources\TeamHistoricalStandingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeamHistoricalStanding extends EditRecord
{
    protected static string $resource = TeamHistoricalStandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
