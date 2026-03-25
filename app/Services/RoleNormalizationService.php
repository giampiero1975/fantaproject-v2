<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RoleNormalizationService
{
    public function __construct() {
        Log::debug('RoleNormalizationService: Versione 2025-07-21-ULTIMA-MAPPA-V10-NUMERIC-R-FIX');
    }

    // Mappa ruoli descrittivi (API/FBRef) → P, D, C, A
    private const MAIN_ROLE_MAPPING = [
        'goalkeeper' => 'P', 'gk' => 'P',
        'centre-back' => 'D', 'left-back' => 'D', 'right-back' => 'D',
        'full-back' => 'D', 'defensive' => 'D', 'central defender' => 'D',
        'df' => 'D', 'cb' => 'D', 'lb' => 'D', 'rb' => 'D', 's' => 'D', 'defence' => 'D',
        'central midfield' => 'C', 'defensive midfield' => 'C', 'attacking midfield' => 'C',
        'midfielder' => 'C', 'wing' => 'C', 'winger' => 'C', 'central midfielder' => 'C',
        'cm' => 'C', 'dm' => 'C', 'am' => 'C', 'mf' => 'C', 'cc' => 'C',
        'midfield' => 'C', 'left midfield' => 'C', 'right midfield' => 'C',
        'forward' => 'A', 'striker' => 'A', 'centre-forward' => 'A',
        'left winger' => 'A', 'right winger' => 'A',
        'secondary striker' => 'A', 'falso nueve' => 'A',
        'att' => 'A', 'fw' => 'A', 'st' => 'A', 'cf' => 'A', 'lw' => 'A', 'rw' => 'A',
        'offence' => 'A',
    ];

    // Mappa ruoli Mantra (rm) → ruolo principale P/D/C/A
    // Usata sia per normalizeFromRoster() (fallback numeric r) sia come lookup standalone
    private const MANTRA_TO_MAIN_ROLE = [
        'por' => 'P',
        'dc' => 'D', 'dd' => 'D', 'ds' => 'D', 'e' => 'D', 'b' => 'D',
        'm' => 'C', 'c' => 'C', 't' => 'C', 'w' => 'C',
        'a' => 'A', 'pc' => 'A',
    ];

    // Mappa ruoli dettagliati/tattici → set standardizzato Mantra
    private const STANDARD_DETAILED_POSITION_MAPPING = [
        'goalkeeper' => 'POR', 'gk' => 'POR', 'por' => 'POR',
        'centre-back' => 'DC', 'left-back' => 'DS', 'right-back' => 'DD',
        'full-back' => 'E', 'central defender' => 'DC',
        'cb' => 'DC', 'lb' => 'DS', 'rb' => 'DD', 's' => 'DC',
        'dd' => 'DD', 'ds' => 'DS', 'dc' => 'DC', 'e' => 'E', 'b' => 'B',
        'defence' => 'D', 'df' => 'D',
        'central midfield' => 'C', 'defensive midfield' => 'M', 'attacking midfield' => 'T',
        'midfielder' => 'C',
        'cm' => 'C', 'dm' => 'M', 'am' => 'T', 'mf' => 'C', 'cc' => 'C',
        'c' => 'C', 'm' => 'M', 't' => 'T',
        'left midfield' => 'E', 'right midfield' => 'E',
        'wing' => 'W', 'winger' => 'W',
        'midfield' => 'C',
        'forward' => 'A', 'striker' => 'PC', 'centre-forward' => 'PC',
        'secondary striker' => 'T', 'falso nueve' => 'PC',
        'left winger' => 'W', 'right winger' => 'W',
        'st' => 'PC', 'cf' => 'PC', 'lw' => 'W', 'rw' => 'W',
        'a' => 'A', 'pc' => 'PC', 'tr' => 'T', 'ad' => 'W', 'as' => 'W',
        'offence' => 'A', 'fw' => 'A',
        'p' => 'POR', 'gk' => 'POR', 'mf' => 'C',
    ];

    public function normalize(array $rawData, string $source): array
    {
        switch ($source) {
            case 'roster_xlsx':
                return $this->normalizeFromRoster($rawData);
            case 'football_data_api':
                return $this->normalizeFromFootballDataApi($rawData);
            case 'fbref_scraping':
                return $this->normalizeFromFbref($rawData);
            case 'historical_stats_xlsx':
                return $this->normalizeFromHistoricalStats($rawData);
            default:
                Log::warning("RoleNormalizationService: Fonte sconosciuta: {$source}");
                return ['role_main' => null, 'detailed_position' => [], 'source_role_raw' => null];
        }
    }

    /**
     * Normalizza dal Roster Ufficiale (XLSX Fantagazzetta).
     *
     * Colonne rilevanti:
     *   r  = codice ruolo (in versioni recenti è un intero, es. 0/1/2/3/4;
     *            in versioni legacy era la lettera P/D/C/A)
     *   rm = ruoli Mantra semicolon-separated, es. "Pc" / "M;C" / "Por"
     *
     * Strategia:
     *   1. Se r è già una lettera in {P,D,C,A} → usa direttamente.
     *   2. Altrimenti → deriva role_main dal primo token di rm
     *      tramite MANTRA_TO_MAIN_ROLE.
     */
    private function normalizeFromRoster(array $row): array
    {
        $mainRoleRaw    = $row['r']  ?? null;
        $mantraRolesRaw = $row['rm'] ?? '';

        $roleMain = null;
        $detailedPositionStandardized = [];

        // ── Strategia 1: r come lettera P/D/C/A ─────────────────────────────
        $trimmedMainRole = Str::upper(trim((string)$mainRoleRaw));
        if (in_array($trimmedMainRole, ['P', 'D', 'C', 'A'], true)) {
            $roleMain = $trimmedMainRole;
        }

        // ── Strategia 2: r numerico → deriva da rm ────────────────────────────
        if (is_null($roleMain) && !empty(trim((string)$mantraRolesRaw))) {
            $firstMantra = strtolower(trim(explode(';', trim((string)$mantraRolesRaw))[0]));
            if (isset(self::MANTRA_TO_MAIN_ROLE[$firstMantra])) {
                $roleMain = self::MANTRA_TO_MAIN_ROLE[$firstMantra];
                Log::debug("RoleNormalizationService: r='{$mainRoleRaw}' numerico, derivato role_main='{$roleMain}' da rm='{$mantraRolesRaw}'.");
            } else {
                Log::error("RoleNormalizationService: r='{$mainRoleRaw}' non lettera, rm='{$mantraRolesRaw}' non mappato. role_main=NULL.");
            }
        }

        if (is_null($roleMain)) {
            Log::error("RoleNormalizationService: Ruolo non determinabile (r='{$mainRoleRaw}', rm='{$mantraRolesRaw}'). NULL.");
        }

        // ── Detailed position dai ruoli Mantra (rm) ───────────────────────────
        if (!empty(trim((string)$mantraRolesRaw))) {
            $mantraRoles = explode(';', trim((string)$mantraRolesRaw));
            foreach ($mantraRoles as $mantraRole) {
                $normalizedKey = Str::lower(trim($mantraRole));
                if (isset(self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedKey])) {
                    $detailedPositionStandardized[] = self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedKey];
                } else {
                    Log::warning("RoleNormalizationService: Ruolo Mantra non mappato: '{$mantraRole}'. Inclusione diretta.");
                    $detailedPositionStandardized[] = Str::upper(trim($mantraRole));
                }
            }
            $detailedPositionStandardized = array_unique($detailedPositionStandardized);
        }

        return [
            'role_main'         => $roleMain,
            'detailed_position' => array_values($detailedPositionStandardized),
            'source_role_raw'   => $mainRoleRaw,
        ];
    }

    private function normalizeFromFootballDataApi(array $rawData): array
    {
        $apiPosition = $rawData['position'] ?? null;
        $mainRole = null;
        $detailedPositionStandardized = [];

        if (!empty($apiPosition)) {
            if (Str::contains($apiPosition, [',', ';', '/'])) {
                Log::warning("RoleNormalizationService: Posizione API composta: '{$apiPosition}'");
            }
            $positionsToMap = array_map('trim', explode(',', $apiPosition));
            foreach ($positionsToMap as $pos) {
                $normalizedApiPosition = Str::lower($pos);
                if (isset(self::MAIN_ROLE_MAPPING[$normalizedApiPosition]) && is_null($mainRole)) {
                    $mainRole = self::MAIN_ROLE_MAPPING[$normalizedApiPosition];
                } else {
                    Log::warning("RoleNormalizationService: Ruolo API non mappato in MAIN_ROLE_MAPPING: '{$pos}'.");
                }
                if (isset(self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedApiPosition])) {
                    $detailedPositionStandardized[] = self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedApiPosition];
                } else {
                    Log::warning("RoleNormalizationService: Posizione API non mappata in STANDARD_DETAILED_POSITION_MAPPING: '{$pos}'.");
                }
            }
            $detailedPositionStandardized = array_unique($detailedPositionStandardized);
        }

        $output = [
            'role_main'         => $mainRole,
            'detailed_position' => array_values($detailedPositionStandardized),
            'source_role_raw'   => $apiPosition,
        ];
        Log::debug("RoleNormalizationService: normalizeFromFootballDataApi('{$apiPosition}'): " . json_encode($output));
        return $output;
    }

    private function normalizeFromFbref(array $rawData): array
    {
        $fbrefPosition = $rawData['position_abbr'] ?? null;
        $mainRole = null;
        $detailedPositionStandardized = [];

        if (!empty($fbrefPosition)) {
            if (Str::contains($fbrefPosition, [',', ';', '/'])) {
                Log::warning("RoleNormalizationService: Posizione FBRef composta: '{$fbrefPosition}'");
            }
            $positionsToMap = array_map('trim', explode(',', $fbrefPosition));
            foreach ($positionsToMap as $pos) {
                $normalizedFbrefPosition = Str::lower($pos);
                if (isset(self::MAIN_ROLE_MAPPING[$normalizedFbrefPosition]) && is_null($mainRole)) {
                    $mainRole = self::MAIN_ROLE_MAPPING[$normalizedFbrefPosition];
                } else {
                    Log::warning("RoleNormalizationService: Ruolo FBRef non mappato: '{$pos}'.");
                }
                if (isset(self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedFbrefPosition])) {
                    $detailedPositionStandardized[] = self::STANDARD_DETAILED_POSITION_MAPPING[$normalizedFbrefPosition];
                } else {
                    Log::warning("RoleNormalizationService: Posizione FBRef non mappata: '{$pos}'.");
                }
            }
            $detailedPositionStandardized = array_unique($detailedPositionStandardized);
        }

        return [
            'role_main'         => $mainRole,
            'detailed_position' => array_values($detailedPositionStandardized),
            'source_role_raw'   => $fbrefPosition,
        ];
    }

    private function normalizeFromHistoricalStats(array $rawData): array
    {
        $historicalRoleRaw = $rawData['role_from_excel'] ?? null;
        $mainRole = null;

        $trimmedRole = Str::upper(trim((string)$historicalRoleRaw));
        if (in_array($trimmedRole, ['P', 'D', 'C', 'A'], true)) {
            $mainRole = $trimmedRole;
        } else {
            Log::warning("RoleNormalizationService: Ruolo storico non valido: '{$historicalRoleRaw}'. NULL.");
        }

        return [
            'role_main'         => $mainRole,
            'detailed_position' => [],
            'source_role_raw'   => $historicalRoleRaw,
        ];
    }

    public function filterGenericDetailedPositions(array $detailedPositions, ?string $mainRole): array
    {
        if (empty($detailedPositions) || is_null($mainRole)) {
            return $detailedPositions;
        }
        $filteredPositions = $detailedPositions;
        $specificSubRolesForGeneric = [
            'D' => ['DD', 'DS', 'DC', 'E', 'B'],
            'C' => ['M', 'T', 'W', 'E'],
            'A' => ['PC', 'T', 'W'],
        ];
        $genericRole = Str::upper($mainRole);
        if (in_array($genericRole, $filteredPositions, true) && isset($specificSubRolesForGeneric[$genericRole])) {
            $hasSpecific = false;
            foreach ($specificSubRolesForGeneric[$genericRole] as $specificCode) {
                if (in_array($specificCode, $filteredPositions, true)) {
                    $hasSpecific = true;
                    break;
                }
            }
            if ($hasSpecific) {
                $filteredPositions = array_values(array_filter($filteredPositions, fn($pos) => $pos !== $genericRole));
            }
        }
        return $filteredPositions;
    }
}
