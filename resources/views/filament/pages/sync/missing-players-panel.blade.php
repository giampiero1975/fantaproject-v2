<div class="w-full h-full -m-6" style="min-height: 85vh;">
    @php
        $url = \App\Filament\Resources\PlayerResource::getUrl('index', [
            'tableFilters' => [
                'roster_filter' => ['season_id' => $seasonId],
                'fbref_status' => ['value' => '0'],
            ],
            'minimal' => 1
        ]);
    @endphp
    
    <iframe 
        src="{{ $url }}" 
        class="w-full border-0"
        style="height: 85vh; width: 100%; background: transparent;"
        loading="lazy"
    ></iframe>
</div>
