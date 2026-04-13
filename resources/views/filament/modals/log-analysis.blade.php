<div class="space-y-4 text-sm">

    @if($logs->isEmpty())
        <div class="text-center py-8">
            <x-filament::icon icon="heroicon-o-clock" class="h-12 w-12 text-gray-300 mx-auto mb-3"/>
            <p class="text-gray-400 italic">Nessuna sincronizzazione registrata in <code>import_logs</code>.</p>
            <p class="text-xs text-gray-400 mt-1">Esegui "Sincronizza Rose API" per creare il primo record.</p>
        </div>
    @else
        {{-- Tabella cronologica --}}
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <th class="px-3 py-2 text-left text-gray-500">ID</th>
                    <th class="px-3 py-2 text-left text-gray-500">Stagione</th>
                    <th class="px-3 py-2 text-left text-gray-500">Data</th>
                    <th class="px-3 py-2 text-center text-gray-500">Stato</th>
                    <th class="px-3 py-2 text-center text-gray-500">Elaborati</th>
                    <th class="px-3 py-2 text-center text-gray-500">Aggiornati</th>
                    <th class="px-3 py-2 text-center text-gray-500">Creati (L4)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 font-mono text-gray-400">{{ $log->id }}</td>
                        <td class="px-3 py-2 font-bold text-primary-600 dark:text-primary-400">
                            {{ $log->season ? \App\Helpers\SeasonHelper::formatYear($log->season->season_year) : 'N/A' }}
                        </td>
                        <td class="px-3 py-2 font-mono text-gray-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $statusCss = match($log->status) {
                                    'successo'  => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'in_corso'  => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'fallito'   => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    default     => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusCss }}">
                                {{ $log->status }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">{{ $log->rows_processed ?? '—' }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-green-600">{{ $log->rows_updated ?? '—' }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-blue-600">{{ $log->rows_created ?? '—' }}</td>
                    </tr>
                    {{-- Dettaglio orfani --}}
                    @if($log->details && str_contains($log->details, 'Orfani'))
                        <tr class="bg-amber-50/50 dark:bg-amber-900/10">
                            <td colspan="7" class="px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400 italic font-mono">
                                {{ Str::limit($log->details, 180) }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        {{-- Totali aggregati --}}
        <div class="grid grid-cols-3 gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
                <p class="text-xl font-bold text-green-700 dark:text-green-400">{{ $logs->sum('rows_updated') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Tot. Aggiornati</p>
            </div>
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 text-center">
                <p class="text-xl font-bold text-blue-700 dark:text-blue-400">{{ $logs->sum('rows_created') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Tot. Creati (L4)</p>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                <p class="text-xl font-bold text-gray-700 dark:text-gray-300">{{ $logs->count() }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Run registrati</p>
            </div>
        </div>

        <p class="text-xs text-gray-400 text-right italic">Fonte: <code>import_logs</code> — Cronologia ultime 10 operazioni di sincronizzazione rose (Direct & Historical)</p>
    @endif

</div>
