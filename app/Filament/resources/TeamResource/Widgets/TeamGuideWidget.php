<?php

namespace App\Filament\Resources\TeamResource\Widgets;

use Filament\Widgets\Widget;

class TeamGuideWidget extends Widget
{
    protected static string $view = 'filament.resources.team-resource.widgets.team-guide-widget';
    
    protected int | string | array $columnSpan = 'full';
}