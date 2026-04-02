<x-filament-widgets::widget>
    <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-amber-500">
        <button 
            type="button" 
            @click="isCollapsed = !isCollapsed" 
            class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none"
        >
            <x-filament::icon 
                icon="heroicon-o-academic-cap" 
                class="h-6 w-6 text-amber-500" 
            />
            <div class="flex-1 text-left">
                <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    MANUALE OPERATIVO: Ciclo Anagrafica Squadre (v2.0)
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
                        Inizializza l'universo delle squadre. Recupera i <strong>Nomi Ufficiali</strong>, gli <strong>ID API</strong> e i <strong>Loghi (Crest)</strong>. Senza questa base, non è possibile legare i giocatori alle statistiche FBref.
                    </p>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">Come funziona (Logica API)</h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Il sistema interroga <em>Football-Data.org</em>. Se non forzi una stagione, lo script tenta in ordine: <strong>2026 -> 2025 -> 2024</strong> finché non trova dati validi. 
                    </p>
                    <ul class="text-xs list-disc ml-4 text-gray-500 italic">
                        <li>Usa l'ID API per evitare duplicati.</li>
                        <li>Aggiorna i loghi se sono cambiati.</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Workflow Consigliato</h4>
                    <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 shadow-sm">
                        <ol class="list-decimal ml-4 space-y-1 text-xs">
                            <li><strong>STEP 1:</strong> Vai in 'Gestione Stagione' e usa 'Sincronizza Stagioni da Api' per creare l'anno.</li>
                            <li><strong>STEP 2:</strong> Torna qui in '1. Squadre', clicca su 'Sincronizza Squadre' e scegli l'anno.</li>
                            <li><strong>STEP 3:</strong> Seleziona la sorgente (API o FBref) e avvia il download.</li>
                        </ol>
                    </div>
                </div>

            </div>

            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-[10px] uppercase text-gray-400">
                <span>Riferimento: TeamsImportFromApi.php</span>
                <span>Stato Sistema: v2.0 Gourmet Ready</span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>