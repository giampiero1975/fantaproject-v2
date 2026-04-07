<?php

namespace App\Helpers;

use Carbon\Carbon;

class SeasonHelper
{
    /**
     * Restituisce l'anno della stagione calcistica attuale.
     * Logica: Se siamo prima di Agosto (mese < 8), la stagione è anno corrente - 1.
     * Esempio: Marzo 2026 -> Stagione 2025 (2025/26).
     */
    public static function getCurrentSeason(): int
    {
        $now = Carbon::now();
        return $now->month < 8 ? $now->year - 1 : $now->year;
    }

    /**
     * Restituisce un array associativo [anno => label] per i filtri.
     * Include la stagione in corso.
     */
    public static function getLookbackSeasons(int $years = 4): array
    {
        $current = self::getCurrentSeason();
        $seasons = [];
        $seasons[$current] = self::formatYear($current) . " (In Corso)";
        
        $lastConcluded = $current - 1;
        for ($i = 0; $i < $years; $i++) {
            $year = $lastConcluded - $i;
            $seasons[$year] = self::formatYear($year);
        }

        krsort($seasons);
        return $seasons;
    }

    /**
     * Restituisce SOLO le stagioni CONCLUSE per lo storico classifiche.
     * Esempio: se ora è 2025/26, restituisce 2024/25, 2023/24, 2022/23, 2021/22.
     */
    public static function getCompletedLookbackSeasons(int $years = 4): array
    {
        $current = self::getCurrentSeason();
        $lastConcluded = $current - 1;
        $seasons = [];
        
        for ($i = 0; $i < $years; $i++) {
            $year = $lastConcluded - $i;
            $seasons[$year] = self::formatYear($year);
        }

        krsort($seasons);
        return $seasons;
    }

    /**
     * Formatta un anno nel formato YYYY/YY (es. 2025 -> 2025/26)
     */
    public static function formatYear(int $year): string
    {
        $next = substr((string)($year + 1), -2);
        return "{$year}/{$next}";
    }

    /**
     * Formatta un intervallo di stagioni (es. Dal 2021/22 al 2024/25)
     */
    public static function formatRange(int $startYear, int $endYear): string
    {
        if ($startYear === $endYear) {
            return "Stagione " . self::formatYear($startYear);
        }
        return "Dal " . self::formatYear($startYear) . " al " . self::formatYear($endYear);
    }

    /**
     * Restituisce le stagioni che hanno dati collegati (team_season o standings).
     */
    public static function getPresentSeasons(): array
    {
        $yearsInHistory = \Illuminate\Support\Facades\DB::table('team_historical_standings')
            ->distinct()
            ->pluck('season_year')
            ->toArray();

        return \App\Models\Season::whereHas('teams')
            ->orWhereIn('season_year', $yearsInHistory)
            ->orderBy('season_year', 'desc')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => self::formatYear($s->season_year)])
            ->toArray();
    }
}
