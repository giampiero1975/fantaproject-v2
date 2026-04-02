<x-filament-panels::page>
    <!-- MANUALE OPERATIVO -->
    @include('filament.pages.manage-season.guide')

    <!-- WIDGET LOOKBACK MONITOR (4 ANNI STORICI) -->
    @if ($lookbackStatus && $lookbackStatus['current_year'])
        <div class="mb-6">
            <x-filament::section 
                title="Monitor Salute Storico (Lookback 4 Anni)" 
                icon="heroicon-o-chart-bar"
                :description="$lookbackStatus['is_ready'] ? 'Dati storici completi e pronti per il modello predittivo.' : 'Attenzione: lo storico predittivo non è completo.'"
            >
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <!-- Progress Circle / Summary -->
                    <div class="flex items-center space-x-4">
                        <div class="relative h-16 w-16">
                            <svg class="h-16 w-16 transform -rotate-90">
                                <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200 dark:text-gray-700" />
                                <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="8" fill="transparent" 
                                    stroke-dasharray="175.9" 
                                    stroke-dashoffset="{{ 175.9 * (1 - ($lookbackStatus['ready_count'] / $lookbackStatus['target_count'])) }}" 
                                    class="{{ $lookbackStatus['is_ready'] ? 'text-success-500' : ($lookbackStatus['ready_count'] > 0 ? 'text-warning-500' : 'text-danger-500') }}" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center text-sm font-bold">
                                {{ $lookbackStatus['ready_count'] }}/{{ $lookbackStatus['target_count'] }}
                            </div>
                        </div>
                        <div>
                            <p class="text-lg font-bold leading-none">Storico Predittivo</p>
                            <p class="text-xs text-gray-500 mt-1 uppercase tracking-wider font-semibold">Basato su Stagione {{ $lookbackStatus['current_year'] }}</p>
                        </div>
                    </div>

                    <!-- Years Details Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 flex-1">
                        @foreach ($lookbackStatus['years'] as $yearData)
                            <div class="p-3 rounded-lg border {{ $yearData['is_complete'] ? 'bg-success-50 border-success-200 dark:bg-success-950/20 dark:border-success-800' : 'bg-danger-50 border-danger-200 dark:bg-danger-950/20 dark:border-danger-800' }}">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-bold">{{ $yearData['year'] }}</span>
                                    <x-filament::icon 
                                        :icon="$yearData['status']['icon']" 
                                        class="h-4 w-4 {{ 'text-' . $yearData['status']['color'] . '-500' }}" 
                                    />
                                </div>
                                <div class="text-[10px] uppercase font-bold text-gray-500">
                                    {{ $yearData['status']['label'] }} ({{ $yearData['teams_count'] }}/20)
                                </div>
                                @if (!$yearData['is_api_supported'])
                                    <div class="mt-2">
                                        <x-filament::badge color="warning" size="xs" icon="heroicon-o-cpu-chip">
                                            FBref Ready
                                        </x-filament::badge>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (!$lookbackStatus['is_ready'])
                    <div class="mt-4 p-4 bg-danger-50 dark:bg-danger-950/20 rounded-lg border-l-4 border-danger-500 flex items-center gap-3">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 text-danger-500" />
                        <div class="text-sm text-danger-700 dark:text-danger-400">
                            <strong>Dati Insufficienti:</strong> Mancano le rose complete per alcune stagioni storiche. Il modello predittivo potrebbe non essere accurato.
                        </div>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- CARD STATO LOCALE -->
        <x-filament::section title="Stato Locale (Database)" icon="heroicon-o-server">
            @if ($localSeasonState)
                <div class="space-y-4">
                    <p><strong>Stagione (Label):</strong> {{ $localSeasonState->season_year }} / {{ substr((string)($localSeasonState->season_year + 1), 2) }}</p>
                    <p><strong>Stagione Attuale (ID):</strong> {{ $localSeasonState->id }}</p>
                    <p><strong>Inizio:</strong> {{ $localSeasonState->start_date ? $localSeasonState->start_date->format('d/m/Y') : 'N/A' }}</p>
                    <p><strong>Fine (end_date):</strong> {{ $localSeasonState->end_date ? $localSeasonState->end_date->format('d/m/Y') : 'N/A' }}</p>
                    <p>
                        <strong>Flag is_current:</strong> 
                        <x-filament::badge color="success">TRUE</x-filament::badge>
                    </p>
                </div>
            @else
                <div class="py-6 text-center text-gray-500">
                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-8 w-8 mx-auto text-gray-400 mb-2" />
                    <p>Nessuna stagione attiva nel Database (Tabula Rasa).</p>
                    <p class="text-xs mt-2">Usa il bottone "Forza Verifica Stagione su API" per iniziare.</p>
                </div>
            @endif
        </x-filament::section>

        <!-- CARD STATO API -->
        <x-filament::section title="Stato API Ufficiale (Live Discovery)" icon="heroicon-o-globe-alt">
            @if ($apiSeasonState)
                <!-- Blocchi esistenti API -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-2">
                        <strong>Risultato Verifica:</strong>
                        <x-filament::badge :color="$apiSeasonState['color']">
                            {{ $apiSeasonState['label'] }}
                        </x-filament::badge>
                    </div>

                    @if ($apiSeasonState['status'] === \App\Services\SeasonMonitorService::STATUS_ERROR)
                        <div class="p-4 bg-danger-50 text-danger-600 rounded-lg">
                            <p class="font-bold">Errore di connessione:</p>
                            <p class="text-sm mt-1">{{ $apiSeasonState['description'] }}</p>
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ $apiSeasonState['description'] }}</p>
                            
                            @if(isset($apiSeasonState['api_data']))
                                <p><strong>Nuovo ID Rilevato:</strong> {{ $apiSeasonState['api_id'] }}</p>
                                <p><strong>Nuovo Inizio:</strong> {{ $apiSeasonState['api_data']['startDate'] ?? 'N/A' }}</p>
                                <p><strong>Nuova Fine (Programmata):</strong> {{ $apiSeasonState['api_data']['endDate'] ?? 'N/A' }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="py-6 text-center text-gray-500">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-8 w-8 mx-auto text-gray-400 mb-2" />
                    <p>Nessun controllo effettuato ultimamente.</p>
                    <p class="text-xs pt-2">Clicca il bottone in alto a destra per interrogare i server di football-data.org in tempo reale.</p>
                    
                    @php
                        $isExpired = false;
                        if ($localSeasonState && $localSeasonState->end_date) {
                            $augustFirst = \Carbon\Carbon::create($localSeasonState->end_date->year, 8, 1)->startOfDay();
                            if (\Carbon\Carbon::now()->isAfter($augustFirst)) {
                                $isExpired = true;
                            }
                        }
                    @endphp
                    
                    @if($isExpired)
                        <div class="mt-4 p-3 bg-warning-50 text-warning-600 rounded border border-warning-200 text-sm font-semibold">
                            Attenzione: la stagione locale è scaduta da mesi. Effettua subito la verifica.
                        </div>
                    @endif
                </div>
            @endif
        </x-filament::section>

    </div>

    <!-- Nuova Sezione Tabella -->
    <x-filament::section title="Anagrafica Storica Stagioni" icon="heroicon-o-table-cells" class="mt-6">
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
