<?php
use App\Models\Team;

$map = [
    'FC Internazionale Milano' => 'Inter',
    'AC Milan' => 'Milan',
    'Juventus FC' => 'Juventus',
    'Atalanta BC' => 'Atalanta',
    'Bologna FC 1909' => 'Bologna',
    'AS Roma' => 'Roma',
    'SS Lazio' => 'Lazio',
    'ACF Fiorentina' => 'Fiorentina',
    'SSC Napoli' => 'Napoli',
    'Torino FC' => 'Torino',
    'Udinese Calcio' => 'Udinese',
    'Genoa CFC' => 'Genoa',
    'Hellas Verona FC' => 'Verona',
    'AC Monza' => 'Monza',
    'US Lecce' => 'Lecce',
    'Cagliari Calcio' => 'Cagliari',
    'Empoli FC' => 'Empoli',
    'Frosinone Calcio' => 'Frosinone',
    'US Sassuolo Calcio' => 'Sassuolo',
    'US Salernitana 1919' => 'Salernitana',
];

foreach ($map as $official => $short) {
    Team::where('official_name', $official)->update(['name' => $short]);
    echo "Updated $official to $short\n";
}
