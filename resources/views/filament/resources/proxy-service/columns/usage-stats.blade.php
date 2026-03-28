@php
    $record = $getState();
    if (!$record instanceof \App\Models\ProxyService) {
        $record = $getRecord();
    }
    
    $percentage = $record->limit_monthly > 0 
        ? min(100, ($record->current_usage / $record->limit_monthly) * 100) 
        : 0;
    
    $color = $percentage > 90 ? '#ef4444' : ($percentage > 70 ? '#f59e0b' : '#3b82f6');
@endphp

<div class="flex flex-col gap-1 w-full min-w-[150px] p-1">
    <!-- Container Bar -->
    <div class="w-full bg-gray-200 rounded h-4 dark:bg-gray-700 relative overflow-hidden ring-1 ring-inset ring-gray-950/5 shadow-inner box-border">
        
        <!-- Layer 1: Progress (z-10) -->
        <div class="absolute inset-y-0 left-0 transition-all duration-1000 ease-out z-10" 
             style="width: {{ $percentage }}%; background: {{ $color }}; opacity: 0.85;">
        </div>
        
        <!-- Layer 2: Label (z-20) - Centrato, font ridotto, normale, nero -->
        <div class="absolute inset-0 flex items-center justify-center z-20 pointer-events-none">
            <span class="text-[8px] font-normal tracking-tight text-black dark:text-black">
                {{ number_format($record->current_usage, 0, ',', '.') }}
            </span>
        </div>
    </div>
</div>
