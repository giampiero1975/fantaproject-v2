<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
	
	// Configurazione per il servizio API delle statistiche dei giocatori
    'player_stats_api' => [
        'default_provider' => env('PLAYER_STATS_API_PROVIDER', 'football_data_org'),
        'providers' => [
            'football_data_org' => [
                'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4/'),
                'api_key_name' => env('FOOTBALL_DATA_API_KEY_NAME', 'X-Auth-Token'),
                'api_key' => env('FOOTBALL_DATA_API_KEY'),
                'serie_a_competition_id' => env('FOOTBALL_DATA_SERIE_A_ID', 'SA'), // <-- Chiave importante
                'serie_b_competition_id' => env('FOOTBALL_DATA_SERIE_B_ID', 'SB'),
            ],
        ],
    ],

    'fbref' => [
        'serie_a_url' => env('FBREF_SERIE_A_URL', 'https://fbref.com/en/comps/11/Serie-A-Stats'),
    ],

    'scrapingbee' => [
        'api_key' => env('SCRAPINGBEE_KEY'),
    ],
];
