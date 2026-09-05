<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | GOAT Output Paths
    |--------------------------------------------------------------------------
    |
    | All generated file destinations are configurable here. Each entry
    | may be an absolute path or a path relative to the application base.
    | Publish this file with: php artisan vendor:publish --tag=goat-config
    |
    */

    'paths' => [
        'model'      => app_path('Models'),
        'migration'  => database_path('migrations'),
        'request'    => app_path('Http/Requests'),
        'resource'   => app_path('Http/Resources'),
        'controller' => app_path('Http/Controllers'),
        'service'    => app_path('Services'),
        'repository' => app_path('Repositories'),
        'policy'     => app_path('Policies'),
        'test'       => base_path('tests/Feature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generate Toggle
    |--------------------------------------------------------------------------
    |
    | Globally enable or disable individual artifact types. This is combined
    | with --only / --except CLI flags at generation time.
    |
    */

    'generate' => [
        'model'      => true,
        'migration'  => true,
        'request'    => true,
        'resource'   => true,
        'controller' => true,
        'service'    => true,
        'repository' => true,
        'policy'     => true,
        'test'       => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Overrides
    |--------------------------------------------------------------------------
    |
    | If your application uses non-standard namespaces, override them here.
    | They must match the PSR-4 autoload paths for the directories above.
    |
    */

    'namespaces' => [
        'model'      => 'App\\Models',
        'request'    => 'App\\Http\\Requests',
        'resource'   => 'App\\Http\\Resources',
        'controller' => 'App\\Http\\Controllers',
        'service'    => 'App\\Services',
        'repository' => 'App\\Repositories',
        'policy'     => 'App\\Policies',
        'test'       => 'Tests\\Feature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stub Customization
    |--------------------------------------------------------------------------
    |
    | GOAT first looks for stubs published to resource_path('stubs/vendor/goat')
    | and falls back to the package's default stubs. Publish with:
    | php artisan vendor:publish --tag=goat-stubs
    |
    */

    'stubs_path' => resource_path('stubs/vendor/goat'),

];
