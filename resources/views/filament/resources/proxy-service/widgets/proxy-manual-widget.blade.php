<x-filament-widgets::widget>
    <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-blue-500">
        <button 
            type="button" 
            @click="isCollapsed = !isCollapsed" 
            class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none"
        >
            <x-filament::icon 
                icon="heroicon-o-server-stack" 
                class="h-6 w-6 text-blue-500" 
            />
            <div class="flex-1 text-left">
                <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    MANUALE OPERATIVO: CENTRALE PROXY & SCRAPER (v6.0)
                </span>
                <span class="text-xs text-gray-500">Benvenuto nella console di controllo del "carburante" digitale. Clicca per espandere.</span>
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
                    <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">🧠 Smart Rotation (v6.0)</h4>
                    <ul class="text-gray-600 dark:text-gray-300 space-y-1">
                        <li><strong>Crediti:</strong> Il sistema sceglie il provider con più saldo residuo.</li>
                        <li><strong>Tie-break:</strong> A parità di saldo, preferisce il costo JS minore (es. Crawlbase).</li>
                        <li><strong>Failover:</strong> Se un proxy fallisce (401/403/429), passa al secondo in classifica.</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-purple-700 dark:text-purple-400 uppercase tracking-wider text-xs">🕹️ Guida ai Comandi</h4>
                    <ul class="text-gray-600 dark:text-gray-300 space-y-1">
                        <li><strong>⚡ Test:</strong> Verifica la Key su <em>example.com</em>.</li>
                        <li><strong>🔄 Sync Balance:</strong> Sincronizzazione massiva di tutti i 5 provider attivi.</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">📊 Diagnostica</h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Log avanzati in <code>proxy_audit.log</code> per tracciare ogni switch di rotazione e motivo del cambio.
                    </p>
                </div>

            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h4 class="font-bold text-orange-700 dark:text-orange-400 uppercase tracking-wider text-xs mb-3 flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-4 w-4" />
                    🛠️ Sviluppatore: Aggiunta Nuovo Proxy
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-[11px]">
                    <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                        <span class="font-bold block text-blue-600 mb-1">1. Database</span>
                        Inserire record in <code>proxy_services</code> con Name, Slug, Base URL e API Key.
                    </div>
                    <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                        <span class="font-bold block text-blue-600 mb-1">2. Provider Class</span>
                        Creare <code>[Name]Provider.php</code> in <code>App\Services\ProxyProviders</code>.
                    </div>
                    <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                        <span class="font-bold block text-blue-600 mb-1">3. Registration</span>
                        Registrare lo slug nell'array <code>$providers</code> di <code>ProxyManagerService.php</code>.
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-[10px] uppercase text-gray-400">
                <span>Riferimento: ProxyManagerService.php & SmartRotation</span>
                <span>Stato Sistema: v6.0 Autonomia & Failover Ready</span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
