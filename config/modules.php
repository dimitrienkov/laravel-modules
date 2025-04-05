<?php

use App\Http\Middleware\HandleInertiaRequests;
use DimitrienkoV\LaravelModules\Enums\RouteTypeEnum;

return [
    'paths' => [
        'modules' => 'app/Modules',
        'config' => 'Config',
        'routes' => 'Routes',
        'database' => 'Database',
        'migrations' => 'Migrations',
        'factories' => 'Factories',
        'models' => 'Models',
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
