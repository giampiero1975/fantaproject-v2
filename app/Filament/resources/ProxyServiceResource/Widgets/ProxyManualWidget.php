<?php

namespace App\Filament\Resources\ProxyServiceResource\Widgets;

use Filament\Widgets\Widget;

class ProxyManualWidget extends Widget
{
    protected static string $view = 'filament.resources.proxy-service.widgets.proxy-manual-widget';
    
    protected int | string | array $columnSpan = 'full';
}
