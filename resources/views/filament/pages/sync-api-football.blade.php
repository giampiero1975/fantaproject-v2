<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header con Selettore Stagione e Statistiche --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Audit Stagione</label>
                <select 
                    wire:model.live="selectedSeasonId"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">Seleziona Stagione...</option>
                    @foreach(\App\Models\Season::orderBy('season_year', 'desc')->get() as $season)
                        <option value="{{ $season->id }}">
                            {{ \App\Helpers\SeasonHelper::formatYear($season->season_year) }} {{ $season->is_current ? '(In Corso)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <div class="flex flex-wrap gap-4 justify-end">
                    @foreach($this->getStats() as $stat)
                        <div class="bg-white dark:bg-gray-800 px-4 py-2 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col min-w-[120px]">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $stat['label'] }}</span>
                            <span class="text-lg font-black text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">
                                {{ $stat['value'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Monitor Salute Storico (Lean Table) --}}
        @if ($lookbackStatus && $lookbackStatus['current_year'])
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="bg-gray-50/50 dark:bg-white/5 py-2 px-4 border-b border-gray-100 dark:border-white/5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Stato Stagioni in Pancia (Storico Predittivo)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-[10px] uppercase text-gray-500 font-bold border-b border-gray-100 dark:border-white/5">
                                <th class="px-4 py-2">Stagione</th>
                                <th class="px-4 py-2">Status API</th>
                                <th class="px-4 py-2">Roster</th>
                                <th class="px-4 py-2">Matchati API</th>
                                <th class="px-4 py-2 text-right">Copertura %</th>
                                <th class="px-4 py-2 text-right">Nuovi L4</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($lookbackStatus['years'] as $yearData)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-2 font-black text-gray-900 dark:text-white">
                                        {{ $yearData['year'] }}
                                    </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                @if ($yearData['year'] >= 2023)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                        <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div> API Active
                                                    </span>
                                                    @if($yearData['stats']['is_synced'] ?? false)
                                                        <x-filament::icon 
                                                            icon="heroicon-m-check-badge" 
                                                            class="h-5 w-5 text-primary-500 dark:text-primary-400" 
                                                            tooltip="Sincronizzata ufficialmente"
                                                        />
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                        <div class="h-1.5 w-1.5 rounded-full bg-amber-500"></div> Scraper Req.
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400 font-medium">
                                        {{ $yearData['stats']['total'] ?? 0 }} / 20 Team
                                    </td>
                                    <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">
                                        {{ $yearData['stats']['matched'] ?? 0 }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @php $pct = $yearData['stats']['pct'] ?? 0; @endphp
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-{{ $pct > 90 ? 'green' : ($pct > 50 ? 'warning' : 'danger') }}-500 h-full" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <span class="font-bold {{ $pct > 90 ? 'text-green-600' : 'text-gray-500' }}">{{ $pct }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-amber-600 dark:text-amber-400">
                                        {{ $yearData['stats']['l4'] ?? 0 }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!$selectedSeasonId)
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-8 rounded-2xl text-center">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-12 w-12 text-blue-500 mx-auto mb-4" />
                <h2 class="text-xl font-bold text-blue-900 dark:text-blue-100">Pronto per l'Audit Storico</h2>
                <p class="text-blue-700 dark:text-blue-300 max-w-md mx-auto mt-2">
                    Seleziona una stagione per analizzare lo stato del matching e procedere con la sincronizzazione dei roster.
                </p>
            </div>
        @endif

        {{-- Sezione Risultati Analitici --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Colonna Sinistra: Tabella Matching --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500">Dettaglio Matching API</h3>
                    </div>
                    <div class="p-2">
                        {{ $this->table }}
                    </div>
                </div>
            </div>

            {{-- Colonna Destra: Log e Legenda --}}
            <div class="space-y-6">
                {{-- Legenda Logica --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4">Logica di Matching</h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded bg-primary-100 dark:bg-primary-900/30 text-primary-600 flex items-center justify-center font-bold text-xs">L1</span>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">API ID Match</p>
                                <p class="text-[10px] text-gray-500">Match certo tramite ID univoco API-Football.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded bg-green-100 dark:bg-green-900/30 text-green-600 flex items-center justify-center font-bold text-xs">L2</span>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">Team-Scoped Match</p>
                                <p class="text-[10px] text-gray-500">Match per similitudine nome nella stessa squadra.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center font-bold text-xs">L3</span>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">Global Search</p>
                                <p class="text-[10px] text-gray-500">Ricerca su tutta l'anagrafica (per i trasferiti).</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center font-bold text-xs">L4</span>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">Auto-Creation</p>
                                <p class="text-[10px] text-gray-500">Giocatore non trovato (Riserva/Extra Listone).</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Alert Sicurezza --}}
                <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-700/50">
                    <div class="flex items-center gap-2 text-amber-800 dark:text-amber-400 font-bold text-xs mb-1">
                        <x-filament::icon icon="heroicon-m-shield-check" class="h-4 w-4" />
                        DIRETTIVA NO-DELETE
                    </div>
                    <p class="text-[10px] text-amber-700 dark:text-amber-500">
                        Nessun dato locale (Ruoli, Quotazioni) viene sovrascritto se già presente. Il sistema arricchisce solo le anagrafiche mancanti.
                    </p>
                </div>
            </div>
        </div>

        {{-- Lista Orfani --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6">
            <div class="p-4 border-b border-gray-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-5 w-5 text-amber-500" />
                    <span class="font-semibold text-gray-900 dark:text-white">Orfani - Giocatori senza match API (Stagione Audit)</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/50 text-left">
                            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Squadra</th>
                            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ruolo</th>
                            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Diagnostica</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($this->orphans as $player)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $player->name }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300 text-xs">{{ $player->team_name }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">
                                        {{ $player->role }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @php
                                        $motivoCss = match($player->motivo_colore) {
                                            'amber' => 'text-amber-600 dark:text-amber-400',
                                            'blue'  => 'text-blue-600 dark:text-blue-400',
                                            'red'   => 'text-red-600 dark:text-red-400',
                                            'gray'  => 'text-gray-400 dark:text-gray-500',
                                            default => 'text-gray-500',
                                        };
                                    @endphp
                                    <span class="{{ $motivoCss }} font-medium">{{ $player->sospetto_motivo }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">Nessun orfano trovato per questa stagione.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
