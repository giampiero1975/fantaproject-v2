<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Team;
use App\Models\Season;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchingAuditRealDataTest extends TestCase
{
    /**
     * Esegue l'audit sui calciatori creati come L4 (Nuovi) per verificare
     * se potevano essere matchati come L3 (Trasferimenti).
     */
    public function test_audit_mismatched_transfers()
    {
        echo "\n🔍 AVVIO AUDIT MATCHING - RICERCA TRASFERIMENTI L4 MISMATCH\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        // 1. Identifichiamo i candidati L4 (Creati da API, non hanno fanta_platform_id)
        $l4Players = Player::whereNotNull('api_football_data_id')
            ->whereNull('fanta_platform_id')
            ->with(['rosters.team', 'rosters.season'])
            ->get();

        if ($l4Players->isEmpty()) {
            echo "ℹ️ Nessun calciatore L4 (creato da API) trovato nel database attuale.\n";
            $this->assertTrue(true);
            return;
        }

        $suspiciousCases = 0;

        foreach ($l4Players as $l4) {
            $currentRoster = $l4->rosters->first();
            $currentTeamName = $currentRoster?->team?->name ?? 'Senza Squadra';
            $currentSeasonId = $currentRoster?->season_id;

            // 2. Cerchiamo potenziali match nel DB players (registro globale)
            // Cerchiamo tra quelli che HANNO fanta_platform_id (quindi caricati dal Listone)
            $potentialMatches = Player::where('id', '!=', $l4->id)
                ->whereNotNull('fanta_platform_id')
                ->get();

            foreach ($potentialMatches as $match) {
                $score = $this->calculateSimilarity($l4->name, $match->name);

                if ($score >= 85) {
                    // Verifichiamo il roster del match trovato per la STESSA stagione
                    $matchRoster = PlayerSeasonRoster::where('player_id', $match->id);
                    if ($currentSeasonId) {
                        $matchRoster->where('season_id', $currentSeasonId);
                    }
                    $matchRoster = $matchRoster->with('team')->first();

                    // Se il team è diverso, abbiamo un sospetto mismatch squadra (il problema evidenziato dall'utente)
                    if ($matchRoster && $matchRoster->team_id !== $currentRoster?->team_id) {
                        $suspiciousCases++;
                        
                        echo "❌ L4 CREATO: {$l4->name} (Squadra API: {$currentTeamName})\n";
                        echo "   └─ 🔄 MATCH TROVATO: {$match->name} (Squadra DB: " . ($matchRoster->team?->name ?? 'N/A') . ") | ID: {$match->id} | Score: " . round($score, 1) . "%\n";
                        
                        if ($match->api_football_data_id) {
                            echo "   └─ ⚠️ NOTA: Il record DB ha già un API_ID: {$match->api_football_data_id} (Diverso da {$l4->api_football_data_id})\n";
                        } else {
                            echo "   └─ ✅ NOTA: Il record DB ha API_ID NULLO (Poteva essere un L3 perfetto!)\n";
                        }
                        echo "\n";
                    }
                }
            }
        }

        if ($suspiciousCases === 0) {
            echo "✅ Nessun caso sospetto di mismatch squadra trovato nei dati attuali.\n";
        } else {
            echo "🏁 Audit completato: Trovati {$suspiciousCases} casi sospetti.\n";
        }
        
        $this->assertTrue(true);
    }

    // --- Logica di Similitudine (Copiata da PlayersHistoricalSync per coerenza) ---

    private function calculateSimilarity(string $name1, string $name2): float
    {
        $tokens1 = $this->getNormalizedTokens($name1);
        $tokens2 = $this->getNormalizedTokens($name2);
        
        if (empty($tokens1) || empty($tokens2)) return 0;

        $shortSet = (count($tokens1) <= count($tokens2)) ? $tokens1 : $tokens2;
        $longSet  = ($shortSet === $tokens1) ? $tokens2 : $tokens1;
        $total    = count($shortSet);
        $matches  = 0;

        foreach ($shortSet as $token) {
            foreach ($longSet as $k => $candidate) {
                if ($token === $candidate || 
                    (str_ends_with($token, '.') && str_starts_with($candidate, rtrim($token, '.'))) ||
                    (str_ends_with($candidate, '.') && str_starts_with($token, rtrim($candidate, '.')))
                ) {
                    $matches++;
                    unset($longSet[$k]);
                    continue 2;
                }
            }
            
            $bestFuzzy = 0;
            $bestK = -1;
            foreach ($longSet as $k => $candidate) {
                similar_text($token, $candidate, $pct);
                if ($pct > 80 && $pct > $bestFuzzy) {
                    $bestFuzzy = $pct;
                    $bestK = $k;
                }
            }
            if ($bestK !== -1) {
                $matches += ($bestFuzzy / 100);
                unset($longSet[$bestK]);
            }
        }

        return ($matches / $total) * 100;
    }

    private function getNormalizedTokens(string $name): array
    {
        $n = Str::ascii(strtolower(trim($name)));
        $n = str_replace(["'", '-'], ' ', $n);
        $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return array_values(array_filter(explode(' ', trim($n))));
    }
}
