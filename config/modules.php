<?php

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
    // Opt-in diagnostic logging for module discovery, caching, the loader
    // pipeline and lifecycle operations. Off by default: the package core stays
    // silent unless the host explicitly enables it (typically for field
    // diagnostics of shipped modules). When enabled, events are written to the
    // host-configured channel (null = default channel) and gated by a global
    // severity threshold plus per-category toggles. See docs/logging.md.
    'logging' => [
        'enabled' => env('MODULES_LOGGING', false),
        'channel' => env('MODULES_LOG_CHANNEL'),
        'level' => env('MODULES_LOG_LEVEL', 'debug'),
        'events' => [
            'discovery' => true,
            'cache' => true,
            'pipeline' => true,
            'lifecycle' => true,
        ],
    ],
    'routing' => [
        'types' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => ['api'],
            ],
            'web' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            'inertia' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            // Versioned API is just another config-driven type. Uncomment to load
            // `Routes/api_v1.php` under the `api/v1` prefix with a dedicated
            // `api_v1` middleware group (declare that group in the host app's
            // bootstrap/app.php).
            // 'api_v1' => [
            //     'prefix' => 'api/v1',
            //     'middleware' => ['api_v1'],
            // ],
        ],
    ],
];
