<?php
return [
    'app' => [
        'name' => 'PatriotContracts',
        'base_url' => 'http://localhost/patriotcontracts',
        'timezone' => 'America/Los_Angeles',
        'debug' => true,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'patriotcontracts',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'sources' => [
        'usaspending' => [
            'base_url' => 'https://api.usaspending.gov',
        ],
        'sam_opportunities' => [
            'base_url' => 'https://api.sam.gov/prod/opportunities/v2/search',
            'api_key' => 'SAM-5e4fdb8a-75a6-4eca-b2e3-3680859e6bda',
        ],
        'sam_awards' => [
            'base_url' => 'https://api.sam.gov/contract-awards/v1/search',
            'api_key' => 'SAM-5e4fdb8a-75a6-4eca-b2e3-3680859e6bda',
        ],
        'grants' => [
            'base_url' => 'https://api.grants.gov/v1/api/search2',
        ],
    ],
    'ingest' => [
        'page_size' => 100,
        'max_pages_per_run' => 25,
        'days_back' => 120,
        'ssl' => [
            'verify_ssl' => false,
            'ca_bundle' => 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
        ],
    ],
    'security' => [
        'session_name' => 'patriotcontracts_session',
        'password_algo' => PASSWORD_DEFAULT,
        'csrf_ttl_seconds' => 7200,
        'session_lifetime' => 86400,
        'secure_cookies' => false,
        'token_pepper' => '',
        'api_key_pepper' => '',
        'rate_limit' => [
            'login_window_minutes' => 15,
            'login_max_attempts' => 8,
        ],
    ],
    'mail' => [
        'from_email' => 'no-reply@patriotcontracts.local',
        'from_name' => 'PatriotContracts',
        'reply_to' => 'support@patriotcontracts.local',
    ],
    'plans' => [
        'MEMBER_BASIC' => [
            'stripe_price_id' => '',
        ],
        'MEMBER_PRO' => [
            'stripe_price_id' => '',
        ],
        'API_MEMBER' => [
            'stripe_price_id' => '',
        ],
    ],
    'stripe' => [
        'secret_key' => '',
        'publishable_key' => '',
        'webhook_secret' => '',
    ],
    'oauth' => [
        'google' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'http://localhost/patriotcontracts/oauth/google/callback.php',
        ],
        'apple' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'http://localhost/patriotcontracts/oauth/apple/callback.php',
        ],
        'facebook' => [
            'app_id' => '',
            'app_secret' => '',
            'redirect_uri' => 'http://localhost/patriotcontracts/oauth/facebook/callback.php',
            'graph_version' => 'v19.0',
        ],
    ],
    'api' => [
        'require_key' => false,
        'dev_bypass_localhost' => true,
    ],
];
