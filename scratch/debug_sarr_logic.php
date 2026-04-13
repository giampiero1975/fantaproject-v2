<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;

function getNormalizedTokens(string $name): array
{
    $n = Str::ascii(strtolower(trim($name)));
    $n = str_replace(["'", '-', ',', '`'], ' ', $n);
    $n = preg_replace('/[^a-z0-9\s\.]/', '', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return array_values(array_filter(explode(' ', trim($n))));
}

function calculateSimilarity(string $name1, string $name2): float
{
    $tokens1 = getNormalizedTokens($name1);
    $tokens2 = getNormalizedTokens($name2);
    
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

    $score = ($matches / $total) * 100;
    
    $n1 = implode(' ', $tokens1);
    $n2 = implode(' ', $tokens2);

    if (str_contains($n1, $n2) || str_contains($n2, $n1)) {
        $score += 20;
    }

    return min(100, $score);
}

$nameApi = "Amin Sarr";
$nameDb = "A. Sarr";

echo "API: $nameApi\n";
echo "DB: $nameDb\n";
echo "Tokens API: " . json_encode(getNormalizedTokens($nameApi)) . "\n";
echo "Tokens DB: " . json_encode(getNormalizedTokens($nameDb)) . "\n";
echo "Score: " . calculateSimilarity($nameApi, $nameDb) . "%\n";
