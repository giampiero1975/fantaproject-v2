<?php

namespace Tests\Feature;

use App\Console\Commands\Extraction\PlayersHistoricalSync;
use Tests\TestCase;
use ReflectionMethod;

class MatchingRecalibrationTest extends TestCase
{
    /**
     * Testa la nuova logica di similitudine con Boost Substring e Normalizzazione.
     */
    public function test_similarity_logic_with_boost_and_normalization()
    {
        $command = app(PlayersHistoricalSync::class);
        
        $method = new ReflectionMethod(PlayersHistoricalSync::class, 'calculateSimilarity');
        $method->setAccessible(true);

        $cases = [
            // [Nome API, Nome DB, Mi aspetto Match (> 78)]
            ['Zeki Çelik', 'Celik', true],
            ['Martinez L.', 'Lautaro Martinez', true],
            ['Lautaro', 'Lautaro Martinez', true],
            ['Rodriguez R.', 'Ricardo Rodriguez', true],
            ['K. Kvaratskhelia', 'Khvicha Kvaratskhelia', true],
            ['Lukaku R.', 'Romelu Lukaku', true],
            
            // Casi che NON devono matchare (Falsi Positivi)
            ['G. Provedel', 'Ivan Provedel', false], // Solo cognome uguale, ma G != Ivan
            ['Mario Rossi', 'Luigi Rossi', false],
        ];

        echo "\n🧪 TEST RICALIBRAZIONE MATCHING\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($cases as [$apiName, $dbName, $shouldMatch]) {
            $score = $method->invoke($command, $apiName, $dbName);
            $result = $score >= 78;
            $status = $result === $shouldMatch ? "✅" : "❌";
            
            echo "{$status} '{$apiName}' vs '{$dbName}' | Score: " . round($score, 1) . "% | Atteso: " . ($shouldMatch ? "SI" : "NO") . "\n";
            
            $this->assertEquals($shouldMatch, $result, "Fallito match per {$apiName} vs {$dbName}");
        }
    }
}
