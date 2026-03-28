<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProxyService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'account_endpoint',
        'api_key',
        'username',
        'password',
        'limit_monthly',
        'current_usage',
        'is_active',
        'priority',
        'js_cost',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'username' => 'encrypted',
        'password' => 'encrypted',
        'is_active' => 'boolean',
    ];
}
