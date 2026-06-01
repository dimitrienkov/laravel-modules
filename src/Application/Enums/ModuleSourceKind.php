<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

/**
 * Format of the incoming staging artifact handed to an install/update.
 *
 * `zip` is the only implemented staging format today; git/url/registry sources
 * are roadmap and would each add a case here. Distinct from ModuleOriginKind,
 * which records the fixed provenance persisted in state.json; the two axes grow
 * in parallel.
 */
enum ModuleSourceKind: string
{
    case Zip = 'zip';
}
