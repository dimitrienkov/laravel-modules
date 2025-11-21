<?php

use App\Http\Middleware\HandleInertiaRequests;
use DimitrienkoV\LaravelModules\Enums\RouteTypeEnum;

return [
    'paths' => [
        'modules' => 'app/Modules',
        'integrations' => 'app/Integrations',
        'subsystems' => 'app/Subsystems',
        'config' => 'Config',
        'routes' => 'Routes',
        'database' => 'Database',
        'migrations' => 'Migrations',
        'factories' => 'Factories',
        'models' => 'Models',
        'providers' => 'Providers',
    ],
    'route' => [
        'middlewares' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => 'api',
            ],
            'web' => [
                'prefix' => null,
                'middleware' => [],
            ],
            'inertia' => [
                'prefix' => null,
                'middleware' => HandleInertiaRequests::class,
            ],
        ],
    ],
];
