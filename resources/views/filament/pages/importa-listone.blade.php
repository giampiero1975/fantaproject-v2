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
                            <li>Soft-delete dei giocatori esistenti</li>
                            <li>Parsing del foglio <code>Tutti</code></li>
                            <li>Matching squadra (short_name → name → LIKE)</li>
                            <li>Normalizzazione ruoli (R → P/D/C/A, RM → Mantra)</li>
                            <li>Insert/Update con merge intelligente</li>
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
                ⚠️ L'operazione esegue il soft-delete di tutti i giocatori esistenti prima di inserire i nuovi. Assicurati di avere il file aggiornato.
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
