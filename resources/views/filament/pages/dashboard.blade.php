<x-filament-panels::page>
    <div class="space-y-3">
        @php
            $currentYear  = (int) date('Y');
            $targetSeason = $currentYear - 1;
            $seasonLabel  = $targetSeason . '/' . substr((string)$currentYear, 2);

            // ── Stato step ──────────────────────────────────────────────────
            $s1 = $teamTotal >= 20 ? 'ok' : ($teamTotal > 0 ? 'partial' : 'missing');
            $s2 = !$step1Ok ? 'blocked' : ($teamWithApi >= 20 ? 'ok' : ($teamWithApi > 0 ? 'partial' : 'missing'));
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
        {{-- STEP 1 — Squadre (Anagrafica & API) — FUSED                      --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php
            // Badge unificato: VERDE solo se entrambi 20/20
            if ($teamTotal === 0) {
                $s1 = 'missing';
            } elseif ($teamTotal >= 20 && $teamWithApi >= 20) {
                $s1 = 'ok';
            } else {
                $s1 = 'partial';
            }
            $th = stepTheme($s1);
        @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            {{-- Header --}}
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 1. Squadre
                </span>
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            {{-- Body --}}
            <div class="bg-white px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Dati</p>
                    {{-- Stagione --}}
                    <p class="text-gray-700 mb-1">Stagione target: <strong>{{ $seasonLabel }}</strong></p>
                    {{-- Dual indicator DB + API --}}
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                        <span style="display:inline-flex; align-items:center; gap:4px; background:#f3f4f6; border-radius:5px; padding:3px 10px; font-size:0.8rem;">
                            <span style="color:#6b7280; font-weight:600;">DB</span>
                            <strong style="{{ $teamTotal >= 20 ? 'color:#198754' : ($teamTotal > 0 ? 'color:#d97706' : 'color:#dc2626') }}">
                                {{ $teamTotal }}/20
                            </strong>
                        </span>
                        <span style="display:inline-flex; align-items:center; gap:4px; background:#f3f4f6; border-radius:5px; padding:3px 10px; font-size:0.8rem;">
                            <span style="color:#6b7280; font-weight:600;">API</span>
                            <strong style="{{ $teamWithApi >= 20 ? 'color:#198754' : ($teamWithApi > 0 ? 'color:#d97706' : 'color:#dc2626') }}">
                                {{ $teamWithApi }}/20
                            </strong>
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Condizione</p>
                    @if($teamTotal === 0)
                        <p style="color:#dc2626;">Nessuna squadra per la stagione {{ $seasonLabel }}. Eseguire l'import API.</p>
                    @elseif($teamTotal < 20)
                        <p style="color:#d97706;">{{ $teamTotal }}/20 squadre nel DB — mancano {{ 20 - $teamTotal }}.</p>
                    @elseif($teamWithApi < 20)
                        <p style="color:#d97706;">DB completo, ma {{ 20 - $teamWithApi }} squadre senza <code>api_football_data_id</code>. Re-eseguire sync API.</p>
                    @else
                        <p style="color:#198754;">Tutte le 20 squadre di Serie A caricate con ID API per la stagione {{ $seasonLabel }}.</p>
                    @endif
                </div>
            </div>
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
