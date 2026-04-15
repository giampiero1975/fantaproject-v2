<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Contestuale informativo --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6 border-l-4 border-primary-500">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Centrale Sincronizzazione FBref</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($this->selectedTeamId)
                            🩺 Analisi chirurgica: <span class="font-bold text-primary-600">{{ \App\Models\Team::find($this->selectedTeamId)?->name }}</span> (Stagione {{ $this->selectedSeasonYear ? \App\Helpers\SeasonHelper::formatYear($this->selectedSeasonYear) : '—' }})
                        @else
                            🌍 Visualizzazione Globale: <span class="font-bold">Intera Serie A</span> (Stagione {{ $this->selectedSeasonYear ? \App\Helpers\SeasonHelper::formatYear($this->selectedSeasonYear) : '—' }})
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <x-filament::badge color="success" size="sm">Matching Rigido Attivo</x-filament::badge>
                </div>
            </div>
        </div>
        {{-- Widgets di Intestazione (Gestiti da Filament via getHeaderWidgets) --}}

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Form di Filtro --}}
            <div class="lg:col-span-4 bg-white dark:bg-gray-900 rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6">
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4 text-primary-500" />
                        Configurazione Sync
                    </h3>
                </div>
                {{ $this->form }}
            </div>

            {{-- Statistiche Rapide --}}
            <div class="lg:col-span-8">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach($this->getStats() as $stat)
                        <div class="bg-white dark:bg-gray-900 px-6 py-5 rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col items-center justify-center text-center">
                            <span class="text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ $stat['label'] }}</span>
                            <span @class([
                                'text-3xl font-black tracking-tighter',
                                'text-primary-600' => ($stat['color'] === 'primary'),
                                'text-success-600' => ($stat['color'] === 'success'),
                                'text-amber-600'   => ($stat['color'] === 'warning'),
                                'text-danger-600'  => ($stat['color'] === 'danger'),
                                'text-gray-950 dark:text-white' => ($stat['color'] === 'gray'),
                            ])>
                                {{ $stat['value'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- COPERTURA FBREF — GLOBALE + DETTAGLIO STAGIONE --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Tabella 1 — Copertura Globale Stagioni --}}
            <div class="lg:col-span-6 fi-section rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden border-none">
                <div class="bg-gray-50/50 dark:bg-white/5 py-3 px-6 border-b border-gray-100 dark:border-white/5">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Copertura Globale (per Stagione)</h3>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left">
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400">Stagione</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Totale</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Con FBref</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Mancanti</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">% Copertura</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($this->getSeasonsCoverageRows() as $row)
                                @php
                                    $pct = (float) ($row['coverage_pct'] ?? 0);
                                    $color = $pct >= 90 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $row['season_label'] }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">{{ (int) $row['total_players'] }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">{{ (int) $row['mapped_players'] }}</td>
                                    <td class="px-3 py-2 text-center font-bold text-danger-600">
                                        @php
                                            $missingCount = (int) $row['total_players'] - (int) $row['mapped_players'];
                                            $filterUrl = \App\Filament\Resources\PlayerResource::getUrl('index', [
                                                'tableFilters' => [
                                                    'roster_filter' => ['season_id' => $row['season_id']],
                                                    'fbref_status' => ['value' => '0'],
                                                ]
                                            ]);
                                        @endphp
                                        @if($missingCount > 0)
                                            <button 
                                                wire:click="mountAction('viewMissingPlayers', { seasonId: {{ $row['season_id'] }} })"
                                                wire:key="view-missing-{{ $row['season_id'] }}"
                                                class="font-bold text-danger-600 underline decoration-dotted hover:text-danger-500 transition-colors"
                                            >
                                                {{ $missingCount }}
                                            </button>
                                        @else
                                            0
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span style="background-color: {{ $color }}; color:#fff; font-weight:800; font-size:0.70rem; padding:2px 10px; border-radius:999px; display:inline-block;">
                                            {{ number_format($pct, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-gray-500 text-center">Nessun dato roster trovato.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tabella 2 — Dettaglio Squadre della Stagione (Serie A) --}}
            <div class="lg:col-span-6 fi-section rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden border-none">
                <div class="bg-gray-50/50 dark:bg-white/5 py-3 px-6 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Dettaglio Squadre (Stagione selezionata)</h3>
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-success-500 animate-pulse"></span>
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Live</span>
                    </div>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left">
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400">Squadra</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Totale</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Con FBref</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">% Copertura</th>
                                <th class="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-gray-400 text-center">Azione</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($this->getTeamsCoverageRows() as $row)
                                @php
                                    $pct = (float) ($row['coverage_pct'] ?? 0);
                                    $color = $pct >= 90 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $row['team_name'] }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">{{ (int) $row['total_players'] }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">{{ (int) $row['mapped_players'] }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span style="background-color: {{ $color }}; color:#fff; font-weight:800; font-size:0.70rem; padding:2px 10px; border-radius:999px; display:inline-block;">
                                            {{ number_format($pct, 1) }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        @if($pct < 100)
                                            <x-filament::button 
                                                wire:click="syncSingleTeam({{ $row['team_id'] }})"
                                                wire:loading.attr="disabled"
                                                size="xs"
                                                color="primary"
                                                icon="heroicon-m-play"
                                                icon-alias="panels::pages.sync-fbref.sync-button"
                                                labeled-from="md"
                                                class="shadow-sm"
                                            >
                                                Sync
                                            </x-filament::button>
                                        @else
                                            <x-filament::icon icon="heroicon-m-check-circle" class="mx-auto h-5 w-5 text-success-500" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-gray-500 text-center">Seleziona una stagione per vedere i dettagli.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
