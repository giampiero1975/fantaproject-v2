<?php
require_once 'vendor/autoload.php';
use Illuminate\Support\Str;

$s = 'Mattia Aramu';
$l = 'Mattia Caldara';

$sNorm = strtolower(preg_replace('/[^a-z]/', '', Str::ascii($s)));
$lNorm = strtolower(preg_replace('/[^a-z]/', '', Str::ascii($l)));

similar_text($sNorm, $lNorm, $score);

echo "S: $sNorm\n";
echo "L: $lNorm\n";
echo "Score: $score%\n";
