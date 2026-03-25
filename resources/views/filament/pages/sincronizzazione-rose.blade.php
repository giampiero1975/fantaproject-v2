<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Barra Avanzamento Real-Time (Cache polling) ─────────────────── --}}
        @php $progress = $this->getSyncProgressProperty(); @endphp

        <div wire:poll.3000ms="$refresh"
             class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4
                    {{ $progress['running'] ? 'border-l-4 border-amber-400' : ($progress['done'] ? 'border-l-4 border-green-500' : 'border-l-4 border-gray-200 dark:border-gray-700') }}">

            @if($progress['running'])
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-amber-700 dark:text-amber-400">Sincronizzazione in corso...</span>
                    </div>
                    <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ $progress['percent'] }}%</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-amber-500 h-2.5 rounded-full transition-all duration-700"
                         style="width: {{ $progress['percent'] }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1.5 font-mono">{{ $progress['label'] }}</p>

            @elseif($progress['done'])
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-green-500"/>
                    <span class="text-sm font-semibold text-green-700 dark:text-green-400">Ultima sync completata con successo</span>
                    @if($progress['log_id'])
                        <span class="text-xs text-gray-400 font-mono">(ImportLog #{{ $progress['log_id'] }})</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $progress['label'] }}</p>

            @else
                <div class="flex items-center gap-2 text-gray-400">
                    <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4"/>
                    <span class="text-xs">Nessuna sincronizzazione attiva. Usa il pulsante "Sincronizza Rose API".</span>
                </div>
            @endif
        </div>

        @php
            $cov     = $this->getCoverageProperty();
            $orphans = $this->getOrphansProperty();
            $pctColor = $cov['pct'] >= 90 ? 'green' : ($cov['pct'] >= 70 ? 'amber' : 'red');
        @endphp

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 text-center">
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($cov['total']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Giocatori totali</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 text-center">
                <p class="text-3xl font-bold text-{{ $pctColor }}-600 dark:text-{{ $pctColor }}-400">{{ $cov['pct'] }}%</p>
                <p class="text-xs text-gray-500 mt-1">Copertura API</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 text-center">
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $cov['matched'] }}</p>
                <p class="text-xs text-gray-500 mt-1">Con api_football_data_id</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 text-center">
                <p class="text-3xl font-bold text-{{ $orphans->count() === 0 ? 'green' : 'amber' }}-600 dark:text-{{ $orphans->count() === 0 ? 'green' : 'amber' }}-400">
                    {{ $orphans->count() }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Orfani (no match API)</p>
            </div>
        </div>

        {{-- ── Manuale operativo ───────────────────────────────────────────── --}}
        <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-amber-500">
            <button type="button" @click="isCollapsed = !isCollapsed" class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none">
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-6 w-6 text-amber-500" />
                <div class="flex-1 text-left">
                    <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">MANUALE OPERATIVO: Sincronizzazione Rose API (Step 5)</span>
                    <span class="text-xs text-gray-500">Clicca per espandere le istruzioni</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 text-gray-400 transition duration-200" x-bind:class="{ 'rotate-180': !isCollapsed }" />
            </button>
            <div x-show="!isCollapsed" x-cloak x-transition class="border-t border-gray-200 p-6 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="space-y-2">
                        <h4 class="font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider text-xs">Prerequisiti</h4>
                        <ol class="list-decimal ml-4 space-y-1 text-gray-600 dark:text-gray-300 text-xs">
                            <li>Step 4 — Listone Quotazioni importato</li>
                            <li>Tabella <code>players</code> con ≥ 500 record</li>
                            <li>Chiave <code>FOOTBALL_DATA_API_KEY</code> valida in <code>.env</code></li>
                        </ol>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">Matching a 4 Livelli</h4>
                        <ol class="list-decimal ml-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <li><strong>L1</strong> api_football_data_id già in DB</li>
                            <li><strong>L2</strong> Nome simile nella stessa squadra</li>
                            <li><strong>L3</strong> Nome simile globale (trasferiti)</li>
                            <li><strong>L4</strong> Crea nuovo record (riserve)</li>
                        </ol>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Sicurezza</h4>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 text-xs space-y-1">
                            <p class="text-green-700 dark:text-green-400 font-semibold">✅ Direttiva No-Delete attiva</p>
                            <p class="text-gray-500 dark:text-gray-400">I non-matchati vengono loggati nella tabella Orfani. Ruolo e quotazione dal listone NON vengono sovrascritti.</p>
                        </div>
                    </div>
                </div>

                {{-- UX Feedback info --}}
                <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg px-4 py-3 text-xs text-blue-700 dark:text-blue-400">
                    ℹ️ Il pulsante <strong>"Sincronizza Rose API"</strong> esegue il processo in modo sincrono (~2.5 min). Durante l'elaborazione il browser sembrerà bloccato — è normale. Una notifica verde confermerà il completamento. <strong>Non aggiornare la pagina.</strong>
                </div>
            </div>
        </div>

        {{-- ── Tabella Orfani con Diagnostica ─────────────────────────────── --}}
        @if($orphans->isEmpty())
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 flex items-center gap-3">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-green-500" />
                <div>
                    <p class="text-base font-semibold text-green-700 dark:text-green-400">✅ Copertura Completa</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tutti i giocatori del listone hanno trovato un match nell'API.</p>
                </div>
            </div>
        @else
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="p-4 border-b border-gray-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-5 w-5 text-amber-500" />
                        <span class="font-semibold text-gray-900 dark:text-white">Orfani — Giocatori senza match API</span>
                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                            {{ $orphans->count() }} giocatori
                        </span>
                    </div>
                    {{-- Legenda motivi --}}
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span>⬇️ Retrocessa</span>
                        <span>🔤 Mancato Match</span>
                        <span>❓ Non in DB</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50 text-left">
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Squadra</th>
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ruolo</th>
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sospetto Motivo</th>
                                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fanta ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($orphans as $player)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-2 text-gray-400 font-mono text-xs">{{ $player->id }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $player->name }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300 text-xs">{{ $player->team_name }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $roleCss = match($player->role) {
                                                'P' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                                'D' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                                'C' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'A' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                default => 'bg-gray-100 text-gray-500',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $roleCss }}">
                                            {{ $player->role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        @php
                                            $motivoCss = match($player->motivo_colore) {
                                                'amber' => 'text-amber-600 dark:text-amber-400',
                                                'blue'  => 'text-blue-600 dark:text-blue-400',
                                                'red'   => 'text-red-600 dark:text-red-400',
                                                default => 'text-gray-500',
                                            };
                                        @endphp
                                        <span class="{{ $motivoCss }} font-medium">{{ $player->sospetto_motivo }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-400 font-mono text-xs">{{ $player->fanta_platform_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-100 dark:border-white/5 text-xs text-gray-400 text-right">
                    Nessun record verrà eliminato (Direttiva No-Delete). Usa "Analizza Log Sync" per il dettaglio dei motivi.
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
