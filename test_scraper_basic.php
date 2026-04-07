<?php

$url = 'https://api.scraperapi.com/';
$params = [
    'api_key' => 'bb9c2be5115269c16b31266c45a56404',
    'url' => 'https://fbref.com/en/comps/11/Serie-A-Stats'
];

$ch = curl_init($url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: " . $status . "\n";
if ($status == 200) {
    if (strpos($body, 'stats_table') !== false) {
        echo "Contiene tabelle statistiche: SI (Perfetto per il Crawler!)\n";
    } else {
        echo "Contiene tabelle statistiche: NO (Possibile captcha o caricamento Javascript mancante)\n";
    }
} else {
    echo "Errore.\n";
}
