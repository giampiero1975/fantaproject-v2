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
     * @param int $years Numero di stagioni da includere (inclusa la corrente).
     * Esempio: $years = 5 per stagione 2025 -> [2021, 2022, 2023, 2024, 2025].
     */
    public static function getLookbackSeasons(int $years = 4): array
    {
        $current = self::getCurrentSeason();
        $seasons = [];
        
        for ($i = ($years - 1); $i >= 0; $i--) {
            $year = $current - $i;
            $seasons[$year] = (string)$year;
        }

        return $seasons;
    }
}
