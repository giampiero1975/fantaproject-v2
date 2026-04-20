<?php

namespace Database\Seeders;

use App\Models\ProxyService;
use Illuminate\Database\Seeder;

class ProxyServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Definiamo i parametri in un array PHP
        $params = [
            'render'  => true, 
            'premium' => true
        ];

        $triade = [
            [
                'id' => 1,
                'name' => 'ScraperAPI MetMi',
                'slug' => 'scraperapi-metmi',
                'base_url' => 'http://proxy-server.scraperapi.com:8001',
                'api_key' => 'bbcaefcbfd3ce3a1c9c2c2e5d7b46a9d',
                'default_params' => json_encode($params),
                'limit_monthly' => 5000,
                'js_cost' => 10,
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'id' => 2,
                'name' => 'ScraperAPI Inwind',
                'slug' => 'scraperapi-inwind',
                'base_url' => 'http://proxy-server.scraperapi.com:8001',
                'api_key' => 'bb9c2be5115269c16b31266c45a56404',
                'default_params' => json_encode($params),
                'limit_monthly' => 1000,
                'js_cost' => 10,
                'is_active' => true,
                'priority' => 2,
            ],
            [
                'id' => 3,
                'name' => 'ScraperAPI GMail',
                'slug' => 'scraperapi-gmail',
                'base_url' => 'http://proxy-server.scraperapi.com:8001',
                'api_key' => '4a400f9d25a28f5c804a453ac51d152a',
                'default_params' => json_encode($params),
                'limit_monthly' => 1000,
                'js_cost' => 10,
                'is_active' => true,
                'priority' => 3,
            ],
        ];

        foreach ($triade as $proxy) {
            ProxyService::updateOrCreate(
                ['id' => $proxy['id']],
                $proxy
            );
        }
    }
}
