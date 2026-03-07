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
            'noticedesc_base_url' => 'https://api.sam.gov/prod/opportunities/v1/noticedesc',
            'api_key' => '',
        ],
        'sam_awards' => [
            'base_url' => 'https://api.sam.gov/contract-awards/v1/search',
            'api_key' => '',
        ],
        'grants' => [
            'base_url' => 'https://api.grants.gov/v1/api/search2',
        ],
    ],
    'ingest' => [
        'page_size' => 100,
        'max_pages_per_run' => 25,
        'days_back' => 120,
        // SAM transport proxy defaults: proxy is disabled unless explicitly enabled/configured.
        'sam_http' => [
            'disable_proxy' => true,
            'proxy_url' => '',
        ],
        'ssl' => [
            'verify_ssl' => true,
            'ca_bundle' => 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
        ],
    ],
    'security' => [
        'session_name' => 'patriotcontracts_session',
        'password_algo' => PASSWORD_DEFAULT,
        'csrf_ttl_seconds' => 7200,
        'session_lifetime' => 86400,
        'secure_cookies' => true,
        'token_pepper' => 'replace_with_long_random_secret',
        'api_key_pepper' => 'replace_with_second_long_random_secret',
        'rate_limit' => [
            'login_window_minutes' => 15,
            'login_max_attempts' => 8,
        ],
    ],
    'mail' => [
        'from_email' => 'no-reply@example.com',
        'from_name' => 'PatriotContracts',
        'reply_to' => 'support@example.com',
    ],
    'plans' => [
        'MEMBER_BASIC' => [
            'stripe_price_id' => 'price_basic_monthly',
        ],
        'MEMBER_PRO' => [
            'stripe_price_id' => 'price_pro_monthly',
        ],
        'API_MEMBER' => [
            'stripe_price_id' => 'price_api_monthly',
        ],
    ],
    'stripe' => [
        'secret_key' => 'sk_live_or_test_key',
        'publishable_key' => 'pk_live_or_test_key',
        'webhook_secret' => 'whsec_...',
    ],
    'oauth' => [
        'google' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'https://your-domain.com/oauth/google/callback.php',
        ],
        'apple' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'https://your-domain.com/oauth/apple/callback.php',
        ],
        'facebook' => [
            'app_id' => '',
            'app_secret' => '',
            'redirect_uri' => 'https://your-domain.com/oauth/facebook/callback.php',
            'graph_version' => 'v19.0',
        ],
    ],
    'api' => [
        'require_key' => true,
        'dev_bypass_localhost' => false,
    ],
];
