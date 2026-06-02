<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

/**
 * The eight lifecycle operations that emit diagnostic events.
 *
 * Each case maps to a lifecycle UseCase and becomes the `{op}` segment of a log
 * event name (e.g. `lifecycle.install.started`). The read-only ListModulesUseCase
 * is intentionally absent — it mutates nothing and emits no lifecycle events.
 */
enum LifecycleOperation: string
{
    case Install = 'install';
    case Update = 'update';
    case Remove = 'remove';
    case Enable = 'enable';
    case Disable = 'disable';
    case Scaffold = 'scaffold';
    case Optimize = 'optimize';
    case ClearCache = 'clear_cache';
}
