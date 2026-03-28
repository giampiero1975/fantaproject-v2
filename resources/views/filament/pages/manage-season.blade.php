<x-filament-panels::page>
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
