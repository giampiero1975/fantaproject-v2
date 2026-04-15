<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
$user->password = bcrypt('giampiero');
$user->save();

echo "Password aggiornata per: " . $user->email . "\n";
