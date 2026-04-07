<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ProxyService;
use App\Services\ProxyManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProxyRotationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_picks_the_proxy_with_most_remaining_credits()
    {
        // Creiamo 3 proxy con diversi saldi
        ProxyService::create([
            'name' => 'Low Credits',
            'slug' => 'low',
            'limit_monthly' => 1000,
            'current_usage' => 900, // 100 rimanenti
            'is_active' => true,
            'priority' => 1,
            'js_render' => false,
            'base_url' => 'https://api.example.com',
            'api_key' => 'dummy-key-1',
        ]);

        ProxyService::create([
            'name' => 'High Credits',
            'slug' => 'high',
            'limit_monthly' => 1000,
            'current_usage' => 100, // 900 rimanenti
            'is_active' => true,
            'priority' => 1,
            'js_render' => false,
            'base_url' => 'https://api.example.com',
            'api_key' => 'dummy-key-2',
        ]);

        $manager = app(ProxyManagerService::class);
        $best = $manager->getActiveProxy();

        $this->assertEquals('high', $best->slug);
    }
}
