<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;

/**
 * Test di ricalibrazione della logica di matching per nomi calciatori.
 *
 * La funzione calculateSimilarity è stata rimossa da PlayersHistoricalSync
 * (ora usa ERP-FAST in-memory). Questo test valida la stessa logica di
 * similarità che era nel command, ora mantenuta qui come unit test autonomo
 * per documentazione e regressione.
 */
class MatchingRecalibrationTest extends TestCase
{
    /**
     * Testa la logica di similitudine con Boost Substring e Normalizzazione.
     */
    public function test_similarity_logic_with_boost_and_normalization(): void
    {
        $cases = [
            // [Nome API, Nome DB, Mi aspetto Match (>= 78)]
            ['Zeki Çelik',          'Celik',               true],
            ['Martinez L.',         'Lautaro Martinez',    true],
            ['Lautaro',             'Lautaro Martinez',    true],
            ['Rodriguez R.',        'Ricardo Rodriguez',   true],
            ['K. Kvaratskhelia',    'Khvicha Kvaratskhelia', true],
            ['Lukaku R.',           'Romelu Lukaku',       true],

            // Casi che NON devono matchare (Falsi Positivi)
            ['G. Provedel',         'Ivan Provedel',       false], // Solo cognome uguale
            ['Mario Rossi',         'Luigi Rossi',         false],
        ];

        echo "\n🧪 TEST RICALIBRAZIONE MATCHING\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($cases as [$apiName, $dbName, $shouldMatch]) {
            $score  = $this->calculateSimilarity($apiName, $dbName);
            $result = $score >= 78;
            $status = $result === $shouldMatch ? '✅' : '❌';

            echo "{$status} '{$apiName}' vs '{$dbName}' | Score: " . round($score, 1) . "% | Atteso: " . ($shouldMatch ? 'SI' : 'NO') . "\n";

            $this->assertEquals(
                $shouldMatch,
                $result,
                "Fallito match per '{$apiName}' vs '{$dbName}' (score: " . round($score, 1) . "%)"
            );
        }
    }

    // ── Logica di similarità (mirror della logica storica del command) ─────

    private function calculateSimilarity(string $name1, string $name2): float
    {
        $tokens1 = $this->getNormalizedTokens($name1);
        $tokens2 = $this->getNormalizedTokens($name2);

        if (empty($tokens1) || empty($tokens2)) return 0.0;

        $shortSet = (count($tokens1) <= count($tokens2)) ? $tokens1 : $tokens2;
        $longSet  = ($shortSet === $tokens1) ? $tokens2 : $tokens1;
        $total    = count($shortSet);
        $matches  = 0.0;

        foreach ($shortSet as $token) {
            foreach ($longSet as $k => $candidate) {
                if ($token === $candidate
                    || (str_ends_with($token, '.') && str_starts_with($candidate, rtrim($token, '.')))
                    || (str_ends_with($candidate, '.') && str_starts_with($token, rtrim($candidate, '.')))
                ) {
                    $matches++;
                    unset($longSet[$k]);
                    continue 2;
                }
            }

            $bestFuzzy = 0.0;
            $bestK     = -1;
            foreach ($longSet as $k => $candidate) {
                similar_text($token, $candidate, $pct);
                if ($pct > 80 && $pct > $bestFuzzy) {
                    $bestFuzzy = $pct;
                    $bestK     = $k;
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
