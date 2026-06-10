<?php

declare(strict_types=1);

return [
    'title' => 'Modules',

    'kinds' => [
        'module' => 'Modules',
        'subsystem' => 'Subsystems',
        'integration' => 'Integrations',
    ],

    'columns' => [
        'name' => 'Name',
        'version' => 'Version',
        'enabled' => 'Enabled',
        'group' => 'Group',
        'kind' => 'Kind',
        'namespace' => 'Namespace',
        'path' => 'Path',
        'dependencies' => 'Dependencies',
        'dependents' => 'Dependents',
        'load_order' => 'Load order',
        'feature_values' => 'Feature values',
    ],

    'provenance' => [
        'kind' => 'Source kind',
        'version' => 'Installed version',
        'checksum' => 'Checksum',
    ],

    'actions' => [
        'settings' => 'Settings',
        'detail' => 'Details',
        'remove' => 'Remove',
    ],

    'guard' => [
        'disable_blocked' => 'Cannot be disabled — required by: :modules',
        'remove_blocked' => 'Cannot be removed — required by: :modules',
    ],

    'values' => [
        'yes' => 'Yes',
        'no' => 'No',
        'none' => 'None',
    ],

    'ungrouped' => 'Ungrouped',
];
