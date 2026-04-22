<x-filament-panels::page>
    <div class="space-y-3">
        @php
            $currentYear  = (int) date('Y');
            $targetSeason = $currentYear - 1;
            $seasonLabel  = $targetSeason . '/' . substr((string)$currentYear, 2);

            // ── Stato step (Ricalibrato su Sidebar) ─────────────────────────
            // Step 1: Stagione
            $s1_ok = $currentSeasonModel && $lookbackStatus['is_ready'];
            $s1_status = $s1_ok ? 'ok' : (($currentSeasonModel || $lookbackStatus['ready_count'] > 0) ? 'partial' : 'missing');

            // Step 2: Squadre (ex s1)
            $s2_ok = ($teamTotal >= 20 && $teamWithApi >= 20 && !$fbrefIncomplete);
            $s2_status = !$currentSeasonModel ? 'blocked' : ($s2_ok ? 'ok' : ($teamTotal > 0 ? 'partial' : 'missing'));

            // Step 3: Storico (ex s2)
            $s3_ok = $standingCount >= $standingTarget;
            $s3_status = !$s2_ok ? 'blocked' : ($s3_ok ? 'ok' : ($standingCount > 0 ? 'partial' : 'missing'));

            // Step 4: Tier (ex s3)
            $s4_ok = $teamWithTier >= 20;
            $s4_status = !$s3_ok ? 'blocked' : ($s4_ok ? 'ok' : ($teamWithTier > 0 ? 'partial' : 'missing'));

            // Step 5: Listone (ex s4)
            $s5_ok = $playerFanta >= 400;
            $s5_status = !$s4_ok ? 'blocked' : ($s5_ok ? 'ok' : ($playerFanta > 0 ? 'partial' : 'missing'));

            // Step 6: Calciatori (Sidebar 6)
            $s6_ok = $playerFanta >= 400; // O un altro criterio di salute
            $s6_status = !$s5_ok ? 'blocked' : ($s6_ok ? 'ok' : 'partial');

            // Step 7: Sync (Sidebar 7, ex Step 6)
            $s7_ok = $pct >= 90;
            $s7_status = !$s6_ok ? 'blocked' : ($s7_ok ? 'ok' : ($pct > 0 ? 'partial' : 'missing'));

            $tierSummary = collect($tierDist)->map(fn($c,$t) => "T{$t}: {$c}")->implode(' · ');

            if (!function_exists('stepTheme')) {
                function stepTheme(string $status): array {
                    return match($status) {
                        'ok'      => [
                            'icon'          => '✅',
                            'border_style'  => 'border-left: 4px solid #198754;',
                            'header_style'  => 'background-color: #f0fdf4; border-bottom: 1px solid #bbf7d0;',
                            'badge_style'   => 'background-color: #198754; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                            'badge_label'   => 'Dati Presenti',
                        ],
                        'partial' => [
                            'icon'          => '⚠️',
                            'border_style'  => 'border-left: 4px solid #f59e0b;',
                            'header_style'  => 'background-color: #fffbeb; border-bottom: 1px solid #fde68a;',
                            'badge_style'   => 'background-color: #d97706; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                            'badge_label'   => 'Parziale',
                        ],
                        'missing' => [
                            'icon'          => '❌',
                            'border_style'  => 'border-left: 4px solid #dc2626;',
                            'header_style'  => 'background-color: #fef2f2; border-bottom: 1px solid #fecaca;',
                            'badge_style'   => 'background-color: #dc2626; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                            'badge_label'   => 'Mancante',
                        ],
                        default   => [ // blocked
                            'icon'          => '⏳',
                            'border_style'  => 'border-left: 4px solid #9ca3af;',
                            'header_style'  => 'background-color: #f9fafb; border-bottom: 1px solid #e5e7eb;',
                            'badge_style'   => 'background-color: #6b7280; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                            'badge_label'   => 'In attesa',
                        ],
                    };
                }
            }
        @endphp

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 0 — MONITOR REGIONALE                                       --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); border-left: 4px solid #3b82f6; margin-bottom: 8px;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1e293b; font-size:0.875rem;">
                        📡 0. Monitor Regionale (Hub Football-Data)
                    </span>
                    <div style="display:flex; gap:8px;">
                        @php 
                            $sColor = match($seasonStatus['color'] ?? 'warning') {
                                'success' => '#10b981',
                                'gray' => '#6b7280',
                                default => '#f59e0b',
                            };
                        @endphp
                        <span style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            API: <span style="color:{{ $sColor }}; font-weight:700;">{{ $seasonStatusLabel }}</span>
                        </span>
                        <span style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            Proxy: <span style="color:{{ $proxyStatus['percentage_used'] > 90 ? '#dc2626' : '#10b981' }}; font-weight:700;">{{ 100 - $proxyStatus['percentage_used'] }}% Disponibile</span>
                        </span>
                    </div>
                </div>
                <button wire:click="triggerSeasonSync" wire:loading.attr="disabled" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                    <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="w-3 h-3" />
                    Sincronizza Struttura
                </button>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 1 — GESTIONE STAGIONI                                       --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s1_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 1. Gestione Stagioni
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.pages.manage-season') }}" class="text-xs font-bold text-blue-600 hover:underline">Vedi Storico</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
            @if($s1_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Stagione Attiva</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Anno: <strong>{{ $currentSeasonModel ? $currentSeasonModel->season_year : 'Mancante' }}</strong>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Storico (Lookback)</p>
                        <p style="{{ $lookbackStatus['is_ready'] ? 'color:#198754' : 'color:#d97706' }}">
                            {{ $lookbackStatus['ready_count'] }} / {{ $lookbackStatus['target_count'] }} anni completi
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 2 — Squadre (Anagrafica & API)                               --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s2_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                        {{ $th['icon'] }} 2. Squadre
                    </span>
                    @if($s2_status !== 'blocked')
                    <div style="display:flex; gap:8px; align-items:center;">
                        @php $squadColor = ($teamTotal >= 20) ? '#10b981' : '#f59e0b'; @endphp
                        <span title="Squadre attive" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            Active: <span style="color:{{ $squadColor }}; font-weight:700;">{{ $teamTotal }} / 20</span>
                        </span>
                        @php $apiColor = ($apiMissingCount === 0) ? '#10b981' : '#f59e0b'; @endphp
                        <span title="Mappatura API" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            API: <span style="color:{{ $apiColor }}; font-weight:700;">{{ $teamWithApi }}</span>
                        </span>
                    </div>
                    @endif
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.resources.teams.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Gestisci Squadre</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 3 — Storico Classifiche                                     --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s3_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 3. Storico Classifiche
                </span>
                @if($s3_status !== 'blocked' && $standingCount < $standingTarget)
                    <button wire:click="triggerHistoryScraping" wire:loading.attr="disabled" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                        <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="w-3.5 h-3.5" />
                        Aggiorna Storico
                    </button>
                @endif
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s3_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Piazzamenti:
                            <strong style="{{ $standingCount >= $standingTarget ? 'color:#198754' : ($standingCount > 0 ? 'color:#d97706' : 'color:#dc2626') }}">
                                {{ $standingCount }} / {{ $standingTarget }}
                            </strong>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 4 — Calcolo Tier Squadre                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s4_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 4. Calcolo Tier Squadre
                </span>
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s4_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Tier calcolati:
                            <strong style="{{ $teamWithTier >= 20 ? 'color:#198754' : 'color:#dc2626' }}">
                                {{ $teamWithTier }} / 20
                            </strong>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 5 — Importazione Listone Quotazioni                         --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s5_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 5. Importazione Listone Quotazioni
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.pages.importa-listone') }}" class="text-xs font-bold text-blue-600 hover:underline">Vai a Import</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 6 — Calciatori                                              --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s6_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 6. Calciatori
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.resources.players.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Vedi Calciatori</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
            @if($s6_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Anagrafica</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Totale records: <strong>{{ number_format($playerTotal) }}</strong>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 7 — Sync API-Football                                       --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s7_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 7. Sync API-Football
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.pages.sync-api-football') }}" class="text-xs font-bold text-blue-600 hover:underline">Vai a Sync</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
