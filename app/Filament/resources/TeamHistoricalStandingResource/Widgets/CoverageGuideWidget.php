<?php

namespace App\Filament\Resources\TeamHistoricalStandingResource\Widgets;

use Filament\Widgets\Widget;

class CoverageGuideWidget extends Widget
{
    protected static string $view = 'filament.resources.team-historical-standing-resource.widgets.coverage-guide-widget';
    
    protected int | string | array $columnSpan = 'full';
}
