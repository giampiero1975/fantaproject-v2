<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Info box --}}
        <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-green-500">
            <button type="button" @click="isCollapsed = !isCollapsed" class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none">
                <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-6 w-6 text-green-500" />
                <div class="flex-1 text-left">
                    <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">MANUALE OPERATIVO: Importa Listone (Step 4)</span>
                    <span class="text-xs text-gray-500">Clicca per espandere le istruzioni</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 text-gray-400 transition duration-200" x-bind:class="{ 'rotate-180': !isCollapsed }" />
            </button>
            <div x-show="!isCollapsed" x-cloak x-transition class="border-t border-gray-200 p-6 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="space-y-2">
                        <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Prerequisiti</h4>
                        <ol class="list-decimal ml-4 space-y-1 text-gray-600 dark:text-gray-300 text-xs">
                            <li>Step 1 — Squadre sincronizzate</li>
                            <li>Step 2 — Storico Classifiche importato</li>
                            <li>Step 3 — Tier Squadre calcolati</li>
                        </ol>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">Come funziona</h4>
                        <p class="text-gray-600 dark:text-gray-300 text-xs">
                            Carica il file <strong>Excel ufficiale Fantagazzetta</strong> (.xlsx) scaricato dal portale. Il sistema eseguirà:
                        </p>
                        <ol class="list-decimal ml-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <li>Check duplicati in Anagrafica (ID/Nome)</li>
                            <li>Parsing del foglio <code>Tutti</code></li>
                            <li>Matching squadra (short_name → name)</li>
                            <li>Update/Insert Roster della stagione target</li>
                            <li>Cleanup Ceduti (Stagionale + Globale)</li>
                        </ol>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider text-xs">Log & Tracciabilità</h4>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 shadow-sm text-xs space-y-1">
                            <p class="text-gray-500 dark:text-gray-400">📁 Log analitico:</p>
                            <code class="text-[10px] text-blue-700 dark:text-blue-400">storage/logs/Roster/RosterImport.log</code>
                            <p class="text-gray-500 dark:text-gray-400 mt-2">📋 Tabella importazioni:</p>
                            <code class="text-[10px] text-blue-700 dark:text-blue-400">import_logs (type: roster_quotazioni)</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stato Importazioni --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/30 dark:bg-gray-800/30">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-gray-400" />
                    Stato Importazioni per Stagione
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Stagione</th>
                            <th class="px-6 py-3 font-semibold text-center">Stato</th>
                            <th class="px-6 py-3 font-semibold text-right">Calciatori</th>
                            <th class="px-6 py-3 font-semibold">Dettaglio Ultimo Import</th>
                            <th class="px-6 py-3 font-semibold">File Caricato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($seasonsStatus as $sData)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition duration-150">
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">
                                    {{ $sData['name'] }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <x-filament::badge color="{{ $sData['color'] }}" size="sm">
                                        {{ $sData['status'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-bold text-gray-700 dark:text-gray-300">
                                    {{ number_format($sData['count'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($sData['last_log'])
                                        <div class="flex items-center gap-3 text-[11px]">
                                            <span class="text-gray-400">{{ $sData['last_log']['date'] }}</span>
                                            <span class="flex gap-2">
                                                <span class="px-1.5 py-0.5 rounded bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-400 border border-green-100 dark:border-green-900">
                                                    +{{ $sData['last_log']['created'] }} nuovi
                                                </span>
                                                <span class="px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-400 border border-blue-100 dark:border-blue-900">
                                                    {{ $sData['last_log']['updated'] }} agg.
                                                </span>
                                                <span class="px-1.5 py-0.5 rounded bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-900">
                                                    -{{ $sData['last_log']['ceduti'] }} ceduti
                                                </span>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Nessun dato</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($sData['last_log'] && $sData['last_log']['file_name'])
                                        <div class="flex items-center gap-2">
                                            <button 
                                                wire:click="downloadImportFile({{ $sData['last_log']['id'] }})"
                                                class="flex items-center gap-1.5 px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-primary-500 hover:text-white dark:hover:bg-primary-600 transition duration-200 text-xs font-medium border border-gray-200 dark:border-gray-700"
                                                title="Scarica file originale: {{ $sData['last_log']['file_name'] }}"
                                            >
                                                <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-4 w-4" />
                                                <span class="max-w-[120px] truncate">{{ $sData['last_log']['file_name'] }}</span>
                                            </button>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if(collect($seasonsStatus)->where('status', 'Importata')->isEmpty())
                <div class="px-6 py-4 bg-amber-50/30 dark:bg-amber-900/10">
                    <p class="text-xs text-amber-600 dark:text-amber-400 italic">
                        Nessuna stagione ancora importata. Carica il primo file per iniziare.
                    </p>
                </div>
            @endif
        </div>

        {{-- Upload area --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 flex flex-col items-center justify-center gap-4 text-center">
            <div class="h-16 w-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-8 w-8 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pronto per l'importazione</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Clicca il pulsante <strong>"Carica ed Importa"</strong> in alto a destra per avviare il processo.</p>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg px-4 py-3 text-xs text-amber-700 dark:text-amber-400 max-w-sm">
                ⚠️ L'operazione aggiorna il roster della stagione selezionata e identifica eventuali "Ceduti" confrontando il file con l'anagrafica attuale.
            </div>

            {{-- DANGER: file corretto vs errato --}}
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg px-5 py-4 text-xs max-w-lg text-left space-y-2">
                <p class="font-bold text-red-700 dark:text-red-400 text-sm flex items-center gap-1">
                    🚨 ATTENZIONE — File corretto vs errato
                </p>
                <div class="grid grid-cols-2 gap-3 mt-1">
                    <div class="bg-green-100 dark:bg-green-900/30 rounded p-2 space-y-1">
                        <p class="font-semibold text-green-700 dark:text-green-400">✅ File CORRETTO</p>
                        <p class="text-green-800 dark:text-green-300">
                            <strong>Quotazioni</strong> (Listone stagione corrente)<br>
                            Scaricato da: <em>Fantagazzetta → Listone/Quotazioni</em><br>
                            Contiene: <code>Qti</code>, <code>Qta</code>, <code>R</code>, <code>RM</code>
                        </p>
                    </div>
                    <div class="bg-red-100 dark:bg-red-900/30 rounded p-2 space-y-1">
                        <p class="font-semibold text-red-700 dark:text-red-400">❌ File ERRATO</p>
                        <p class="text-red-800 dark:text-red-300">
                            <strong>Statistiche storiche</strong> (stagioni precedenti)<br>
                            Struttura diversa — mancano<br>
                            <code>Qti</code> e i ruoli base
                        </p>
                    </div>
                </div>
                <p class="text-red-600 dark:text-red-400 mt-1">
                    Caricare il file Statistiche lascerà <code>initial_quotation</code> e i ruoli a <code>NULL</code> per tutti i giocatori.
                </p>
            </div>
        </div>

    </div>
</x-filament-panels::page>
