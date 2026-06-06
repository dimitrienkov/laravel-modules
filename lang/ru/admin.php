<?php

declare(strict_types=1);

return [
    'title' => 'Модули',

    'kinds' => [
        'module' => 'Модули',
        'subsystem' => 'Подсистемы',
        'integration' => 'Интеграции',
    ],

    'columns' => [
        'name' => 'Название',
        'version' => 'Версия',
        'enabled' => 'Включён',
        'group' => 'Группа',
        'kind' => 'Тип',
        'namespace' => 'Namespace',
        'path' => 'Путь',
        'dependencies' => 'Зависимости',
        'dependents' => 'Зависимые',
        'load_order' => 'Порядок загрузки',
        'feature_values' => 'Значения фич',
    ],

    'provenance' => [
        'heading' => 'Источник',
        'kind' => 'Тип источника',
        'version' => 'Установленная версия',
        'checksum' => 'Контрольная сумма',
    ],

    'actions' => [
        'settings' => 'Настройки',
        'detail' => 'Подробнее',
        'remove' => 'Удалить',
    ],

    'guard' => [
        'disable_blocked' => 'Нельзя выключить — требуется модулям: :modules',
        'remove_blocked' => 'Нельзя удалить — требуется модулям: :modules',
    ],

    'toasts' => [
        'enabled' => 'Модуль «:module» включён.',
        'disabled' => 'Модуль «:module» выключен.',
        'removed' => 'Модуль «:module» удалён (создан бэкап).',
        'saved' => 'Настройки сохранены.',
    ],

    'empty' => [
        'dependencies' => 'Нет',
    ],

    'values' => [
        'yes' => 'Да',
        'no' => 'Нет',
        'none' => 'Нет',
    ],

    'ungrouped' => 'Без группы',
];
