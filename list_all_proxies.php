<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;

$proxies = ProxyService::all();
foreach ($proxies as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Slug: {$p->slug} | Priority: {$p->priority} | Active: {$p->is_active}\n";
}
