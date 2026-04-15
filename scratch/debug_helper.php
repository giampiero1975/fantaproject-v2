<?php
require_once 'vendor/autoload.php';
use Illuminate\Support\Str;

class Tester {
    use \App\Traits\FindsPlayerByName;
    
    public function test($n1, $n2) {
        $res = $this->namesAreSimilar($n1, $n2);
        echo "'$n1' vs '$n2' -> " . ($res ? 'TRUE' : 'FALSE') . "\n";
    }
}

$t = new Tester();
$t->test('Ethan Ampadu', 'Mattia Caldara');
$t->test('Mattia Aramu', 'Mattia Caldara');
$t->test('Ceccaroni Pietro', 'Pietro Ceccaroni');
$t->test('Gianluca Busio', 'Busio Gianluca');
