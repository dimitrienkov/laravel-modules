<?php

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    'paths' => [
        'directories' => [
            'app/Modules',
            'app/Integrations',
            'app/Subsystems',
        ],
        'backup' => storage_path('app/module-backups'),
        'state' => storage_path('app/private/modules'),
    ],
    // Map module group codes (meta.group) to human-readable labels.
    // Format: 'code' => 'Human Label'. Labels are rendered in `modules:list`
    // as "Human Label (code)" and are reserved for future module UI.
    // Modules whose group has no entry here fall back to the bare code.
    'groups' => [
        // 'content' => 'Content Management',
        // 'e-commerce' => 'E-Commerce',
    ],
    'routing' => [
        'types' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => [SubstituteBindings::class, ConvertEmptyStringsToNull::class, 'api'],
            ],
            'web' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            'inertia' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
        ],
    ],
];
