<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

/**
 * The single source of truth for the package's architectural layers.
 *
 * Each case backs its module-relative directory (`Application/UseCases`, …,
 * `Domain/VO`), the one place those layer strings are spelled out. The
 * architectural `make:*` generators read the namespace segment from here, while
 * {@see \DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent} reads
 * the relative directory — so the two stay in lock-step by construction.
 *
 * It lives in `Support` (not `Application\Enums`) so `Console\Commands\Make` may
 * depend on it without `Support` ever reaching back into the application layer.
 */
enum ModuleLayer: string
{
    case UseCases = 'Application/UseCases';
    case Actions = 'Application/Actions';
    case Queries = 'Application/Queries';
    case Dtos = 'Application/DTOs';
    case ValueObjects = 'Domain/VO';

    /**
     * The module-relative directory for this layer, e.g. `Application/UseCases`.
     */
    public function relativeDirectory(): string
    {
        return $this->value;
    }

    /**
     * The namespace segment for this layer, e.g. `Application\UseCases`.
     */
    public function namespaceSegment(): string
    {
        return str_replace('/', '\\', $this->value);
    }
}
