<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Traits\FindsPlayerByName;

class PlayerMatchTest extends TestCase
{
    use FindsPlayerByName;

    /**
     * Test della logica di Erosione Ibrida (Hybrid Erosion).
     */
    public function test_names_are_similar_hybrid_erosion()
    {
        $cases = [
            ['K. Kvaratskhelia', 'Khvicha Kvaratskhelia', true],
            ['Lautaro Martínez', 'Lautaro Martinez', true],
            ['T. Hernández', 'Theo Hernandez', true],
            ['Zaccagni M.', 'Mattia Zaccagni', true],
            ['Vlahovic', 'Dusan Vlahovic', true],
            ['Paulo Dybala', 'Paulo Bruno Exequiel Dybala', true],
            ['A. Bastoni', 'Alessandro Bastoni', true],
            ['M. Retegui', 'Mateo Retegui', true],
            ['Nessuna Corrispondenza', 'Lionel Messi', false],
        ];

        foreach ($cases as [$scraped, $db, $expected]) {
            $result = $this->namesAreSimilar($scraped, $db);
            if ($result !== $expected) {
                $t1 = $this->getNormalizedTokens($scraped);
                $t2 = $this->getNormalizedTokens($db);
                echo "\nFAIL: '$scraped' [" . implode(',', $t1) . "] vs '$db' [" . implode(',', $t2) . "] expected " . ($expected ? 'MATCH' : 'NO MATCH') . "\n";
            }
            $this->assertEquals($expected, $result);
        }
    }
}
