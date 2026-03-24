<x-filament-panels::page>
    <div class="bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-white/10 rounded-xl overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                    <th class="px-6 py-4 text-sm font-bold text-gray-500 uppercase">Squadra</th>
                    @foreach($seasons as $season)
                        <th class="px-6 py-4 text-sm font-bold text-center text-gray-500 uppercase">
                            {{ $season }}/{{ substr($season + 1, 2) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach($coverageData as $data)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $data['team_name'] }}
                        </td>
                        @foreach($seasons as $season)
                            <td class="px-6 py-4 text-center">
                                @if($data[$season])
                                    <div class="flex justify-center">
                                        <x-heroicon-s-check-circle class="w-6 h-6" style="color: #16a34a;" />
                                    </div>
                                @else
                                    <div class="flex justify-center">
                                        <x-heroicon-s-x-circle class="w-6 h-6" style="color: #dc2626;" />
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>