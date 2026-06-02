<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

use DimitrienkoV\LaravelModules\Support\ModuleLayer;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
use InvalidArgumentException;

/**
 * An optional skeleton component a module can be scaffolded with.
 *
 * Selected through `make:module --with=` or the interactive multiselect, each
 * case maps to a set of relative directories created under the module root
 * (the mapping itself is owned by ModuleSkeletonBuilder). When no component is
 * selected the scaffolder falls back to the default minimal skeleton instead of
 * this explicit set.
 */
enum ScaffoldComponent: string
{
    case Application = 'application';
    case Config = 'config';
    case Console = 'console';
    case Database = 'database';
    case Domain = 'domain';
    case Http = 'http';
    case Routes = 'routes';
    case Views = 'views';

    /**
     * Human-readable label for the interactive multiselect prompt.
     */
    public function label(): string
    {
        return match ($this) {
            self::Application => 'Application layer (use cases, actions, queries, DTOs)',
            self::Config => 'Config',
            self::Console => 'Console commands',
            self::Database => 'Database (factories, migrations, seeders)',
            self::Domain => 'Domain (models)',
            self::Http => 'HTTP (controllers, middleware)',
            self::Routes => 'Routes',
            self::Views => 'Views',
        };
    }

    /**
     * Module-relative directories this component scaffolds.
     *
     * @return array<int, string>
     */
    public function relativeDirectories(): array
    {
        return match ($this) {
            self::Application => [
                ModuleLayer::UseCases->relativeDirectory(),
                ModuleLayer::Actions->relativeDirectory(),
                ModuleLayer::Queries->relativeDirectory(),
                ModuleLayer::Dtos->relativeDirectory(),
            ],
            self::Config => ['Config'],
            self::Console => [ModuleSegment::Commands->relativeDirectory()],
            self::Database => [
                ModuleSegment::Factories->relativeDirectory(),
                ModuleSegment::Migrations->relativeDirectory(),
                ModuleSegment::Seeders->relativeDirectory(),
            ],
            self::Domain => [ModuleSegment::Models->relativeDirectory(), ModuleLayer::ValueObjects->relativeDirectory()],
            self::Http => [ModuleSegment::Controllers->relativeDirectory(), ModuleSegment::Middleware->relativeDirectory()],
            self::Routes => ['Routes'],
            self::Views => [ModuleSegment::Views->relativeDirectory()],
        };
    }

    /**
     * Build a validated component list from a raw comma-separated `--with` value.
     *
     * An empty (or whitespace-only) string yields an empty selection — the
     * mandatory root/provider scaffold with no optional directories. Unknown or
     * duplicated tokens fail fast before any filesystem work begins.
     *
     * @return array<int, self>
     */
    public static function fromOptionValue(string $raw): array
    {
        $tokens = array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn(string $token): bool => $token !== '',
        );

        return self::fromValues(array_values($tokens));
    }

    /**
     * Validate an already-tokenised list of component values (CLI tokens or the
     * values returned by the interactive multiselect).
     *
     * @param array<int, string> $values
     *
     * @return array<int, self>
     */
    public static function fromValues(array $values): array
    {
        $components = [];

        foreach ($values as $value) {
            $component = self::tryFrom($value);

            if ($component === null) {
                throw new InvalidArgumentException(\sprintf(
                    'Unknown module component [%s]; allowed values: %s.',
                    $value,
                    self::allowedValuesList(),
                ));
            }

            if (\in_array($component, $components, true)) {
                throw new InvalidArgumentException(\sprintf('Duplicate module component [%s].', $value));
            }

            $components[] = $component;
        }

        return $components;
    }

    public static function allowedValuesList(): string
    {
        return implode(', ', array_map(static fn(self $case): string => $case->value, self::cases()));
    }
}
