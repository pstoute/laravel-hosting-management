<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hosting Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default hosting provider that will be used when
    | using the Hosting facade without specifying a driver. You may set this
    | to any of the providers defined in the "providers" array below.
    |
    */

    'default' => env('HOSTING_PROVIDER', 'forge'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for hosting provider data. Caching helps reduce
    | API calls and improves performance. You can configure different TTL values
    | for different types of data.
    |
    */

    'cache' => [
        'enabled' => env('HOSTING_CACHE_ENABLED', true),
        'prefix' => 'hosting:',
        'ttl' => [
            'servers' => 300,       // 5 minutes
            'sites' => 300,         // 5 minutes
            'ssl' => 3600,          // 1 hour
            'databases' => 600,     // 10 minutes
            'deployments' => 0,     // Never cache (real-time)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to avoid hitting provider API limits. The package
    | automatically tracks API calls and will throw a RateLimitException if
    | you're approaching the limit.
    |
    */

    'rate_limits' => [
        'enabled' => env('HOSTING_RATE_LIMIT_ENABLED', true),
        'per_minute' => env('HOSTING_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hosting Providers
    |--------------------------------------------------------------------------
    |
    | Configure your hosting providers here. Each provider requires different
    | credentials. Add the necessary environment variables to your .env file.
    |
    */

    'providers' => [

        'forge' => [
            'driver' => 'forge',
            'api_token' => env('FORGE_API_TOKEN'),
            'api_url' => env('FORGE_API_URL', 'https://forge.laravel.com/api/v1'),
        ],

        'gridpane' => [
            'driver' => 'gridpane',
            'api_token' => env('GRIDPANE_API_TOKEN'),
            'api_url' => env('GRIDPANE_API_URL', 'https://my.gridpane.com/api/v1'),
        ],

        'cloudways' => [
            'driver' => 'cloudways',
            'email' => env('CLOUDWAYS_EMAIL'),
            'api_token' => env('CLOUDWAYS_API_KEY'),
            'api_url' => env('CLOUDWAYS_API_URL', 'https://api.cloudways.com/api/v1'),
        ],

        'kinsta' => [
            'driver' => 'kinsta',
            'api_token' => env('KINSTA_API_KEY'),
            'company_id' => env('KINSTA_COMPANY_ID'),
            'api_url' => env('KINSTA_API_URL', 'https://api.kinsta.com/v2'),
        ],

        'wpengine' => [
            'driver' => 'wpengine',
            'username' => env('WPENGINE_USERNAME'),
            'password' => env('WPENGINE_PASSWORD'),
            'account_id' => env('WPENGINE_ACCOUNT_ID'),
            'api_url' => env('WPENGINE_API_URL', 'https://api.wpengineapi.com/v1'),
        ],

        'ploi' => [
            'driver' => 'ploi',
            'api_token' => env('PLOI_API_TOKEN'),
            'api_url' => env('PLOI_API_URL', 'https://ploi.io/api'),
        ],

        'runcloud' => [
            'driver' => 'runcloud',
            'api_key' => env('RUNCLOUD_API_KEY'),
            'api_secret' => env('RUNCLOUD_API_SECRET'),
            'api_url' => env('RUNCLOUD_API_URL', 'https://manage.runcloud.io/api/v2'),
        ],

        'spinupwp' => [
            'driver' => 'spinupwp',
            'api_token' => env('SPINUPWP_API_TOKEN'),
            'api_url' => env('SPINUPWP_API_URL', 'https://api.spinupwp.com/v1'),
        ],

        'cpanel' => [
            'driver' => 'cpanel',
            'api_url' => env('CPANEL_API_URL'),       // e.g., https://server.example.com:2087
            'api_token' => env('CPANEL_API_TOKEN'),
            'username' => env('CPANEL_USERNAME', 'root'),
        ],

    ],

];
