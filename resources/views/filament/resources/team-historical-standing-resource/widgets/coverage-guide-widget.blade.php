<x-filament-widgets::widget>
    <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-amber-500">
        <button 
            type="button" 
            @click="isCollapsed = !isCollapsed" 
            class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none"
        >
            <x-filament::icon 
                icon="heroicon-o-calendar-days" 
                class="h-6 w-6 text-amber-500" 
            />
            <div class="flex-1 text-left">
                <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    MANUALE OPERATIVO: Storico Classifiche (v2.0)
                </span>
                <span class="text-xs text-gray-500">Clicca per espandere le istruzioni tecniche</span>
            </div>
            <x-filament::icon 
                icon="heroicon-m-chevron-down" 
                class="h-5 w-5 text-gray-400 transition duration-200"
                x-bind:class="{ 'rotate-180': !isCollapsed }"
            />
        </button>

        <div x-show="!isCollapsed" x-cloak x-transition class="border-t border-gray-200 p-6 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                
                <div class="space-y-2">
                    <h4 class="font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider text-xs">Cosa fa</h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Popola lo <strong>Storico Classifiche</strong> estraendo le posizioni finali di Serie A e B del passato. Senza queste informazioni, gli algoritmi di stima della forza per le squadre neopromosse o stabili al prossimo draft saranno fallati.
                    </p>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">Come funziona (Logica FBref)</h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Il sistema lancia un <strong>Bulk Upsert</strong> massivo estraendo le tabelle da FBref tramite <em>ScraperAPI</em> v2.
                    </p>
                    <ul class="text-xs list-disc ml-4 text-gray-500 italic">
                        <li>I log degli import di successo si trovano su <code>import_logs</code>.</li>
                        <li>Traccia le squadre nel Database ottimizzando in Upsert le Query.</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Workflow Consigliato</h4>
                    <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 shadow-sm">
                        <ol class="list-decimal ml-4 space-y-1 text-xs">
                            <li><strong>Verifica:</strong> Vai in "Verifica Copertura".</li>
                            <li><strong>Scraping:</strong> Clicca "Sincronizza Classifiche" per coprire i buchi.</li>
                            <li><strong>Controllo DB:</strong> Verifica i record filtrando per Anno.</li>
                            <li><strong>Prossimo Step:</strong> Vai su anagrafiche dei Calciatori.</li>
                        </ol>
                    </div>
                </div>

            </div>

            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-[10px] uppercase text-gray-400">
                <span>Riferimento: TeamDataService.php</span>
                <span>Stato Sistema: v2.0 Gourmet Ready</span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
