<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

/**
 * The single source of truth for the structural sub-paths a module's runtime
 * artifacts live in (`Domain/Models`, `Http/Controllers`, `Database/Factories`,
 * …) — the segments otherwise spelled out, in both their directory (`/`) and
 * namespace (`\`) forms, across {@see ModuleLayout}, the module-aware `make:*`
 * generators, {@see \DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent}
 * and {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder}.
 *
 * Only segments that recur across more than one of those call-sites live here;
 * single-token or single-use sub-paths (`Config`, `Routes`, `Providers`, `Lang`,
 * `Mail`, `Http/Resources`, …) stay inline, because a one-token directory has no
 * separator-form ambiguity to reconcile and centralising it would add
 * indirection without removing real duplication.
 *
 * It is a sibling of {@see ModuleLayer} (which owns the package's *architectural*
 * layers) and, like it, lives in `Support` so `Console\Commands\Make` and the
 * application layer may both depend on it without `Support` reaching back up.
 */
enum ModuleSegment: string
{
    case Models = 'Domain/Models';
    case Observers = 'Domain/Observers';
    case Policies = 'Domain/Policies';
    case Events = 'Domain/Events';
    case Listeners = 'Domain/Listeners';
    case Controllers = 'Http/Controllers';
    case Middleware = 'Http/Middleware';
    case Requests = 'Http/Requests';
    case Factories = 'Database/Factories';
    case Migrations = 'Database/Migrations';
    case Seeders = 'Database/Seeders';
    case Components = 'View/Components';
    case Commands = 'Console/Commands';
    case Views = 'Resources/views';

    /**
     * The module-relative directory for this segment, e.g. `Domain/Models`.
     */
    public function relativeDirectory(): string
    {
        return $this->value;
    }

    /**
     * The namespace segment for this artifact, e.g. `Domain\Models`. Meaningful
     * only for segments that house namespaced PHP classes — the non-namespaced
     * `Migrations` and `Views` never call it.
     */
    public function namespaceSegment(): string
    {
        return str_replace('/', '\\', $this->value);
    }
}
