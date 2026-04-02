<div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-primary-500 mb-6">
    <button 
        type="button" 
        @click="isCollapsed = !isCollapsed" 
        class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none"
    >
        <x-filament::icon 
            icon="heroicon-o-academic-cap" 
            class="h-6 w-6 text-primary-500" 
        />
        <div class="flex-1 text-left">
            <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">
                MANUALE OPERATIVO: Gestione Ciclo Stagionale (v2.0)
            </span>
            <span class="text-xs text-gray-500">Istruzioni tecniche per il Discovery API e lo storico (Lookback)</span>
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
                <h4 class="font-bold text-primary-700 dark:text-primary-400 uppercase tracking-wider text-xs">Discovery API</h4>
                <p class="text-gray-600 dark:text-gray-300">
                    Il sistema interroga <em>Football-Data.org</em> per identificare la stagione in corso. Se viene rilevato un cambio di ID API, il sistema propone l'aggiornamento automatico dei "contenitori" nel database.
                </p>
            </div>

            <div class="space-y-2">
                <h4 class="font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider text-xs">Lookback (Storico 4 Anni)</h4>
                <p class="text-gray-600 dark:text-gray-300">
                    Per alimentare il <strong>Modello Predittivo</strong>, il sistema necessita di 4 stagioni complete precedenti a quella attuale. 
                </p>
                <ul class="text-xs list-disc ml-4 text-gray-500 italic">
                    <li>I contenitori remoti (2023+) usano dati API ufficiali.</li>
                    <li>Gli anni Legacy (< 2023) usano ID convenzionali per lo scraper di FBref.</li>
                </ul>
            </div>

            <div class="space-y-2">
                <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Letargo Estivo</h4>
                <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 shadow-sm">
                    <p class="text-[11px] text-gray-500 leading-relaxed text-xs">
                        Tra la fine del campionato e il 1° Agosto, il monitor entra in **Blackout Automatico** per risparmiare API rate limits, dato che il provider non rilascia nuovi ID prima di quella data.
                    </p>
                </div>
            </div>

        </div>

        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-[10px] uppercase text-gray-400">
            <span>Riferimento: SeasonMonitorService.php</span>
            <span>Sistema: v2.0 Predictive Ready</span>
        </div>
    </div>
</div>
