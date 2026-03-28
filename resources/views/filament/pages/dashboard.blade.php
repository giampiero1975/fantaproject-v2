<x-filament-panels::page>
    <div class="space-y-3">
        @php
            $currentYear  = (int) date('Y');
            $targetSeason = $currentYear - 1;
            $seasonLabel  = $targetSeason . '/' . substr((string)$currentYear, 2);

            // ── Stato step ──────────────────────────────────────────────────
            $s1 = ($teamTotal >= 20 && $teamWithApi >= 20 && !$fbrefIncomplete) ? 'ok' : ($teamTotal > 0 ? 'partial' : 'missing');
            $s2 = !$step1Ok ? 'blocked' : ($standingCount >= $standingTarget ? 'ok' : ($standingCount > 0 ? 'partial' : 'missing'));
            $s3 = !$step2Ok ? 'blocked' : ($teamWithTier >= 20 ? 'ok' : ($teamWithTier > 0 ? 'partial' : 'missing'));
            $s4 = !$step3Ok ? 'blocked' : ($playerFanta >= 400 ? 'ok' : ($playerFanta > 0 ? 'partial' : 'missing'));
            $s5 = !$step4Ok ? 'blocked' : ($pct >= 90 ? 'ok' : ($pct > 0 ? 'partial' : 'missing'));

            $tierSummary = collect($tierDist)->map(fn($c,$t) => "T{$t}: {$c}")->implode(' · ');

            /**
             * Restituisce gli stili inline e le label per ogni stato.
             * Usiamo inline style invece di classi Tailwind per evitare il purge CSS.
             */
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
        @endphp

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 1 — Consolidamento Status Bar                               --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s1) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); border-left: 4px solid #94a3b8; margin-bottom: 8px;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; background-color: white;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                        1. Squadre
                    </span>
                    
                    {{-- QUATTRO BADGE COMPATTI --}}
                    <div style="display:flex; gap:8px; align-items:center;">
                        {{-- 1. Stagione --}}
                        @php 
                            $seasonColor = match($seasonStatus['color'] ?? 'warning') {
                                'success' => '#10b981',
                                'gray' => '#6b7280',
                                default => '#f59e0b',
                            };
                        @endphp
                        <span title="Stato Stagione" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            Stagione: <span style="color:{{ $seasonColor }}; font-weight:700;">{{ $seasonStatusLabel }}</span>
                        </span>
                        {{-- 2. Squadre --}}
                        @php $squadColor = ($teamsActiveCount >= 20) ? '#10b981' : '#f59e0b'; @endphp
                        <span title="Squadre attive stagione {{ $targetSeason }} su totale storiche" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            Squadre: <span style="color:{{ $squadColor }}; font-weight:700;">{{ $teamsActiveCount }}/{{ $teamsUniqueCount }}</span>
                        </span>
                        {{-- 3. API --}}
                        @php $apiColor = ($apiMissingCount === 0) ? '#10b981' : '#f59e0b'; @endphp
                        <span title="Mancanti / Mappati (Snapshot)" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            API: <span style="color:{{ $apiColor }}; font-weight:700;">{{ $apiMissingCount }} / {{ $apiMappedCount }}</span>
                        </span>
                        {{-- 4. FBref --}}
                        @php $fbrefColor = ($fbrefMissingCount === 0) ? '#10b981' : '#f59e0b'; @endphp
                        <span title="Mancanti / Mappati (Snapshot)" style="font-size:0.75rem; font-weight:600; color:#475569; background:#fff; border:1px solid #e2e8f0; padding:2px 10px; border-radius:12px;">
                            FBref: <span style="color:{{ $fbrefColor }}; font-weight:700;">{{ $fbrefMissingCount }} / {{ $fbrefMappedCount }}</span>
                        </span>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.resources.teams.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Gestisci Squadre</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>




        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 2 — Storico Classifiche                                     --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s2) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 2. Storico Classifiche
                </span>

                @if($s2 !== 'blocked' && $standingCount < $standingTarget)
                    <button wire:click="triggerHistoryScraping" wire:loading.attr="disabled" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                        <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="w-3.5 h-3.5" />
                        Aggiorna Storico
                    </button>
                @endif

                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s2 !== 'blocked')
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
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Condizione</p>
                        <p>Verifica della copertura storica dei piazzamenti per le ultime 5 stagioni.</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 3 — Calcolo Tier Squadre                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s3) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 3. Calcolo Tier Squadre
                </span>
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s3 !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Tier calcolati:
                            <strong style="{{ $teamWithTier >= 20 ? 'color:#198754' : 'color:#dc2626' }}">
                                {{ $teamWithTier }} / 20
                            </strong>
                        </p>
                        @if($tierSummary)
                            <p class="text-gray-400 text-xs mt-0.5 font-mono">{{ $tierSummary }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Condizione</p>
                        @if($teamWithTier === 0)
                            <p style="color:#dc2626;">Tier non ancora calcolati per la stagione {{ $seasonLabel }}.</p>
                        @elseif($teamWithTier < 20)
                            <p style="color:#d97706;">{{ 20 - $teamWithTier }} squadre senza tier assegnato.</p>
                        @else
                            <p style="color:#198754;">Tier calcolati per tutte le 20 squadre. {{ $tierSummary }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 4 — Importazione Listone Quotazioni                         --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s4) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 4. Importazione Listone Quotazioni
                </span>
                <span style="{{ $th['badge_style'] }}">
                    {{ $th['badge_label'] === 'Dati Presenti' ? 'Importato' : $th['badge_label'] }}
                </span>
            </div>
            @if($s4 !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Giocatori con fanta_platform_id:
                            <strong style="{{ $playerFanta >= 400 ? 'color:#198754' : 'color:#dc2626' }}">
                                {{ number_format($playerFanta) }}
                            </strong>
                        </p>
                        @if($lastListone)
                            <p class="text-gray-400 text-xs mt-0.5">Ultima import: {{ $lastListone->created_at->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Condizione</p>
                        @if($playerFanta === 0)
                            <p style="color:#dc2626;">Listone non importato. Caricare il file Excel dalla
                                <a href="{{ route('filament.admin.pages.importa-listone') }}" style="text-decoration:underline;font-weight:600;">pagina dedicata</a>.
                            </p>
                        @elseif($playerFanta < 400)
                            <p style="color:#d97706;">Solo {{ $playerFanta }} giocatori — verificare il file sorgente.</p>
                        @else
                            <p style="color:#198754;">{{ number_format($playerFanta) }} giocatori attivi importati correttamente dal listone.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 5 — Sincronizzazione Rose Serie A                           --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = stepTheme($s5) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 5. Sincronizzazione Rose Serie A
                </span>
                <span style="{{ $th['badge_style'] }}">
                    {{ $th['badge_label'] === 'Dati Presenti' ? 'Sincronizzato' : $th['badge_label'] }}
                </span>
            </div>
            @if($s5 !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Copertura API:
                            <strong style="{{ $pct >= 90 ? 'color:#198754' : ($pct > 0 ? 'color:#d97706' : 'color:#dc2626') }}">
                                {{ $playerApi }} / {{ $playerTotal }} ({{ $pct }}%)
                            </strong>
                        </p>
                        @if($playerOrphan > 0)
                            <p class="text-gray-700 dark:text-gray-200">
                                Orfani listone: <strong style="{{ $playerOrphan > 50 ? 'color:#dc2626' : 'color:#d97706' }}">{{ number_format($playerOrphan) }}</strong>
                            </p>
                        @endif
                        @if($lastSync)
                            <p class="text-gray-400 text-xs mt-0.5">
                                Ultima sync: {{ $lastSync->created_at->format('d/m/Y H:i') }}
                                — Ag={{ $lastSync->rows_updated ?? 0 }} Cr={{ $lastSync->rows_created ?? 0 }}
                            </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Condizione</p>
                        @if($pct == 0)
                            <p style="color:#dc2626;">Sincronizzazione non eseguita. Avviare dalla
                                <a href="{{ route('filament.admin.pages.sincronizzazione-rose') }}" style="text-decoration:underline;font-weight:600;">pagina dedicata</a>.
                            </p>
                        @elseif($pct < 90)
                            <p style="color:#d97706;">Copertura al {{ $pct }}% — sotto la soglia del 90%. Ri-eseguire la sync.</p>
                        @else
                            <p style="color:#198754;">
                                Sincronizzati {{ $playerApi }} su {{ $playerTotal }} con l'API.
                                @if($playerOrphan > 0) Orfani: {{ $playerOrphan }}. @endif
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
