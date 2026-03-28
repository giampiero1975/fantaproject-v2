<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FantasyPointCalculatorService
{
    /**
     * Calcola i fantapunti (solo la parte bonus/malus) basati sulle statistiche fornite e le regole di punteggio.
     */
    public function calculateFantasyPoints(array $stats, array $scoringRules, string $playerRole): float
    {
        // Log::debug("FantasyPointCalculatorService: Ricevute stats: " . json_encode($stats));
        
        $fantasyPoints = 0.0;
        
        // Mappa per associare il ruolo breve (P, D, C, A) alla chiave nelle regole di punteggio
        $roleMap = [
            'P' => 'portiere',
            'D' => 'difensore',
            'C' => 'centrocampista',
            'A' => 'attaccante',
        ];
        $playerRoleKey = $roleMap[strtoupper($playerRole)] ?? 'attaccante';
        
        // --- 1. GOL FATTI ---
        if (isset($stats['goals_scored']) && (float)$stats['goals_scored'] > 0) {
            $goalsScored = (float)$stats['goals_scored'];
            $bonusKey = 'gol_' . $playerRoleKey;
            $defaultGolBonus = (float)($scoringRules['gol_attaccante'] ?? 3.0);
            $fantasyPoints += (float)($scoringRules[$bonusKey] ?? $defaultGolBonus) * $goalsScored;
        }
        
        // --- 2. ASSIST ---
        if (isset($stats['assists']) && (float)$stats['assists'] > 0) {
            $assists = (float)$stats['assists'];
            $bonus = (float)($scoringRules['assist_standard'] ?? $scoringRules['assist'] ?? 1.0);
            $fantasyPoints += $bonus * $assists;
        }
        
        // --- 3. CARTELLINI ---
        if (isset($stats['yellow_cards']) && (float)$stats['yellow_cards'] > 0) {
            $val = (float)$stats['yellow_cards'];
            $malus = (float)($scoringRules['ammonizione'] ?? -0.5);
            $fantasyPoints += $malus * $val;
        }
        if (isset($stats['red_cards']) && (float)$stats['red_cards'] > 0) {
            $val = (float)$stats['red_cards'];
            $malus = (float)($scoringRules['espulsione'] ?? -1.0);
            $fantasyPoints += $malus * $val;
        }
        
        // --- 4. AUTOGOL ---
        if (isset($stats['own_goals']) && (float)$stats['own_goals'] > 0) {
            $val = (float)$stats['own_goals'];
            $malus = (float)($scoringRules['autogol'] ?? -2.0);
            $fantasyPoints += $malus * $val;
        }
        
        // --- 5. RIGORI TIRATI ---
        if (isset($stats['penalties_scored']) && (float)$stats['penalties_scored'] > 0) {
            $val = (float)$stats['penalties_scored'];
            $bonus = (float)($scoringRules['rigore_segnato'] ?? 3.0);
            $fantasyPoints += $bonus * $val;
        }
        if (isset($stats['penalties_missed']) && (float)$stats['penalties_missed'] > 0) {
            $val = (float)$stats['penalties_missed'];
            $malus = (float)($scoringRules['rigore_sbagliato'] ?? -3.0);
            $fantasyPoints += $malus * $val;
        }
        
        // --- 6. SPECIFICHE PORTIERE ---
        if (strtoupper($playerRole) === 'P') {
            
            // A. Rigori Parati
            $pensSaved = $stats['gk_penalties_saved'] ?? $stats['penalties_saved'] ?? 0;
            if ((float)$pensSaved > 0) {
                $bonus = (float)($scoringRules['gk_pens_saved'] ?? $scoringRules['rigore_parato'] ?? 3.0);
                $fantasyPoints += $bonus * (float)$pensSaved;
            }
            
            // B. Gol Subiti
            $goalsConc = $stats['goals_conceded'] ?? $stats['gk_goals_against'] ?? 0;
            if ((float)$goalsConc > 0) {
                $malus = (float)($scoringRules['gk_goals_against'] ?? $scoringRules['gol_subito_portiere'] ?? -1.0);
                $fantasyPoints += $malus * (float)$goalsConc;
            }
            
            // C. CLEAN SHEET (PULITO E STANDARD)
            $cs = $stats['clean_sheet'] ?? 0;
            if ((float)$cs > 0) {
                // Ora ci aspettiamo solo la chiave standard 'clean_sheet',
                // perché ProjectionEngineService ha già normalizzato tutto.
                $bonus = (float)($scoringRules['clean_sheet'] ?? 0.0);
                
                if ($bonus != 0) {
                    $fantasyPoints += $bonus * (float)$cs;
                }
            }
        }
        
        return $fantasyPoints;
    }
}