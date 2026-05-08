<?php

namespace App\Helpers;

class FbrefUrlHelper
{
    public static function getStandingsUrl(?int $year = null, ?\App\Models\League $league = null): string
    {
        $league = $league ?: \App\Models\League::where('name', 'Serie A')->first();

        $compId = $league ? $league->fbref_id : '11';
        $compName = $league ? $league->name : 'Serie A';
        $compSlug = str_replace(' ', '-', $compName);

        if (!$year) {
            return "https://fbref.com/en/comps/{$compId}/{$compSlug}-Stats";
        }

        $nextYear = $year + 1;
        $season = "{$year}-{$nextYear}";

        return "https://fbref.com/en/comps/{$compId}/{$season}/{$season}-{$compSlug}-Stats";
    }

    /**
     * Genera l'URL di dettaglio di una squadra su FBref per una stagione specifica.
     * Esempio corrente: https://fbref.com/en/squads/97c8d94c/Frosinone-Stats
     * Esempio storico: https://fbref.com/en/squads/97c8d94c/2023-2024/Frosinone-Stats
     *
     * @param string $fbrefId L'ID univoco di FBref per la squadra (es: 97c8d94c)
     * @param string $fbrefSlug Lo slug di FBref per la squadra (es: Frosinone-Stats)
     * @param int|null $year L'anno della stagione (es: 2023)
     * @return string|null
     */
    public static function getTeamUrl(string $fbrefId, string $fbrefSlug, ?int $year = null): ?string
    {
        if (empty($fbrefId) || empty($fbrefSlug)) {
            return null;
        }

        if (!$year || $year === SeasonHelper::getCurrentSeason()) {
            return "https://fbref.com/en/squads/{$fbrefId}/{$fbrefSlug}";
        }

        $nextYear = $year + 1;
        $seasonPart = "{$year}-{$nextYear}";

        return "https://fbref.com/en/squads/{$fbrefId}/{$seasonPart}/{$fbrefSlug}";
    }
}
