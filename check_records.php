<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$proxies = \DB::table('proxy_services')->select('id', 'name')->get();
echo "Ci sono " . $proxies->count() . " record nel database.\n";
foreach ($proxies as $p) {
    echo "ID: {$p->id} | Nome: {$p->name}\n";
}
