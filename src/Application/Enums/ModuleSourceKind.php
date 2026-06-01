<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

/**
 * Format of the incoming staging artifact handed to an install/update.
 *
 * Currently only `zip`, but this axis is intentionally extensible (e.g. git,
 * url, registry). Distinct from ModuleOriginKind, which records the fixed
 * provenance persisted in state.json; the two axes grow in parallel.
 */
enum ModuleSourceKind: string
{
    case Zip = 'zip';
}
