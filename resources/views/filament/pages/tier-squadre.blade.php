<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Guida rapida --}}
        <div x-data="{ isCollapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border-l-4 border-amber-500">
            <button type="button" @click="isCollapsed = !isCollapsed" class="flex w-full items-center gap-x-3 p-4 focus-visible:outline-none">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-6 w-6 text-amber-500" />
                <div class="flex-1 text-left">
                    <span class="block text-base font-semibold leading-6 text-gray-950 dark:text-white">MANUALE OPERATIVO: Tier Squadre (v2.0)</span>
                    <span class="text-xs text-gray-500">Clicca per espandere le istruzioni tecniche</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 text-gray-400 transition duration-200" x-bind:class="{ 'rotate-180': !isCollapsed }" />
            </button>
            <div x-show="!isCollapsed" x-cloak x-transition class="border-t border-gray-200 p-6 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="space-y-2">
                        <h4 class="font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider text-xs">Cosa fa</h4>
                        <p class="text-gray-600 dark:text-gray-300">Calcola il <strong>Tier (1-5)</strong> per ogni squadra di Serie A, indicante il livello storico di competitività. Tier 1 = top club costante, Tier 5 = fondo classifica cronico. Usato dal motore di proiezione giocatori.</p>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-xs">Come funziona (Gold Standard v2)</h4>
                        <p class="text-gray-600 dark:text-gray-300">Analizza le ultime <strong>5 stagioni</strong> con pesi <strong>[7,4,2,1,1]</strong> e calcola uno <strong>score normalizzato sui punti</strong> invece della posizione:</p>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded p-2 text-xs font-mono text-blue-800 dark:text-blue-300">
                            score = (1 - pts/(giocate×3)) × 20
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-xs">Serie B: score × 1.615 (CF 0.95 × div.fisso 17/10). Soglie calibrate sullo score <em>post-modulatore</em>:</p>
                        <ul class="text-xs list-disc ml-4 text-gray-500 dark:text-gray-400 italic space-y-0.5">
                            <li><strong class="text-yellow-600 dark:text-yellow-400">Tier 1</strong> — score ≤ 6.5 &nbsp;(Inter, Napoli, Atalanta, Juve, Milan)</li>
                            <li><strong class="text-blue-600 dark:text-blue-400">Tier 2</strong> — score ≤ 8.5 &nbsp;(Roma, Lazio, Fiorentina, Bologna)</li>
                            <li><strong class="text-gray-600 dark:text-gray-400">Tier 3</strong> — score ≤ 10.5 (Sassuolo, Torino, Genoa, Udinese)</li>
                            <li><strong class="text-orange-600 dark:text-orange-400">Tier 4</strong> — score ≤ 12.5 (Como, Parma, Verona, Lecce) <span class="text-orange-400">×1.10</span></li>
                            <li><strong class="text-red-600 dark:text-red-400">Tier 5</strong> — score &gt; 12.5 (Cagliari, Cremonese, Pisa) <span class="text-red-400">×1.10</span></li>
                        </ul>
                        <p class="text-gray-400 dark:text-gray-500 text-[10px] italic">Il modulatore ×1.10 viene applicato prima dell'assegnazione del tier per le squadre T4-5.</p>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-xs">Workflow Consigliato</h4>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 shadow-sm">
                            <ol class="list-decimal ml-4 space-y-1 text-xs">
                                <li><strong>Prerequisito:</strong> Esegui prima "Storico Classifiche" (Step 2).</li>
                                <li><strong>Calcolo:</strong> Clicca "Ricalcola Tier Squadre".</li>
                                <li><strong>Verifica:</strong> Controlla la tabella qui sotto.</li>
                                <li><strong>Prossimo Step:</strong> Sincronizzazione Giocatori.</li>
                            </ol>
                        </div>
                        <div class="mt-2 bg-green-50 dark:bg-green-900/20 rounded p-2 text-[10px] text-green-700 dark:text-green-400">
                            <strong>Calibrazione:</strong> MAE 1.18 · Affinity |Δ|≤3: 94.1%<br>
                            Parametri in <code>config/projection_settings.php</code>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-[10px] uppercase text-gray-400">
                    <span>Riferimento: TeamDataService::updateTeamTiers() · config/projection_settings.php</span>
                    <span>Gold Standard v2 · Points Mode · Affinity 94.1%</span>
                </div>
            </div>
        </div>

        {{-- Tabella Tier --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-white/10 flex items-center justify-between">
                <h3 class="font-semibold text-gray-950 dark:text-white">Classificazione Attuale</h3>
                <span class="text-xs text-gray-500">{{ $this->getTeams()->count() }} squadre di Serie A</span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Squadra</th>
                        <th class="px-4 py-3 text-center">Tier</th>
                        <th class="px-4 py-3 text-left">Descrizione</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse($this->getTeams() as $team)
                        @php
                            $tierInfo = match((int)$team->tier) {
                                1 => ['label' => 'Top Club', 'color' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300', 'dot' => 'bg-yellow-500'],
                                2 => ['label' => 'Medio-Alto', 'color' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300', 'dot' => 'bg-blue-500'],
                                3 => ['label' => 'Medio', 'color' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', 'dot' => 'bg-gray-400'],
                                4 => ['label' => 'Medio-Basso', 'color' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300', 'dot' => 'bg-orange-500'],
                                5 => ['label' => 'Retrocessione', 'color' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300', 'dot' => 'bg-red-500'],
                                default => ['label' => 'N/D', 'color' => 'bg-gray-100 text-gray-500', 'dot' => 'bg-gray-300'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($team->crest_url)
                                        <img src="{{ $team->crest_url }}" alt="{{ $team->name }}" class="h-10 w-10 rounded-full object-cover">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-sm font-bold text-gray-500">{{ substr($team->name, 0, 1) }}</div>
                                    @endif
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $team->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $tierInfo['color'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $tierInfo['dot'] }}"></span>
                                    Tier {{ $team->tier ?? '–' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $tierInfo['label'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                Nessuna squadra trovata. Esegui prima la sincronizzazione squadre.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
