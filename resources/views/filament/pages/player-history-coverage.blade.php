<x-filament-panels::page>
    <div class="space-y-6">
        {{-- I widget di intestazione (CoverageStats) vengono renderizzati automaticamente --}}
        
        <div class="fi-section border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white uppercase tracking-wider">
                        {{ $this->auditMode ? 'Audit Buchi nel Patrimonio' : 'Visualizzazione Dati Caricati' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        {{ $this->auditMode ? 'Giocatori a roster senza memoria storica per la stagione selezionata.' : 'Dati statistici reali estratti dal database per i match riusciti.' }}
                    </p>
                </div>
                
                {{-- Toggle Modalità --}}
                <div class="flex items-center gap-3 bg-gray-100 dark:bg-white/5 p-1 rounded-lg">
                    <button 
                        wire:click="$set('auditMode', true)"
                        @class([
                            'px-4 py-1.5 text-xs font-bold rounded-md transition-all',
                            'bg-white dark:bg-gray-800 shadow-sm text-primary-600' => $this->auditMode,
                            'text-gray-500 hover:text-gray-700' => !$this->auditMode,
                        ])
                    >
                        BUCHI (AUDIT)
                    </button>
                    <button 
                        wire:click="$set('auditMode', false)"
                        @class([
                            'px-4 py-1.5 text-xs font-bold rounded-md transition-all',
                            'bg-white dark:bg-gray-800 shadow-sm text-primary-600' => !$this->auditMode,
                            'text-gray-500 hover:text-gray-700' => $this->auditMode,
                        ])
                    >
                        DATI (SARTORIALE)
                    </button>
                </div>
            </div>

            {{-- Toolbar dei Tab Stagionali --}}
            <div class="px-6 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
                <x-filament::tabs label="Selezione Stagione" class="border-none -mb-px">
                    @foreach($this->getSeasonsForTabs() as $id => $label)
                        <x-filament::tabs.item 
                            :active="$this->activeSeasonId === $id"
                            wire:click="$set('activeSeasonId', {{ $id }})"
                            class="py-4"
                        >
                            {{ $label }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>
            
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
