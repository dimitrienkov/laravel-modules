<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;

/**
 * Pure data-shaping view-model for the admin index: bucket the module DTOs by
 * {@see ModuleKind} and by `meta.group`, in the deterministic order the page
 * renders them.
 *
 * Pulled out of {@see \DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage}
 * so the ordering is unit-testable without booting a MoonShine page/resource; the
 * page keeps only the Tabs/Box/TableBuilder assembly.
 */
final readonly class ModuleIndexGrouping
{
    /**
     * Tab order: subsystems, then integrations, then plain modules.
     *
     * @var list<ModuleKind>
     */
    public const array KIND_ORDER = [
        ModuleKind::Subsystem,
        ModuleKind::Integration,
        ModuleKind::Module,
    ];

    /**
     * Group code for modules without an explicit `meta.group`.
     */
    public const string UNGROUPED = '';

    /**
     * Bucket DTOs by {@see ModuleKind} value, preserving the input (dependency)
     * order within each bucket.
     *
     * @param iterable<ModuleAdminDto> $dtos
     *
     * @return array<string, list<ModuleAdminDto>>
     */
    public function byKind(iterable $dtos): array
    {
        $byKind = [];

        foreach ($dtos as $dto) {
            $byKind[$dto->kind][] = $dto;
        }

        return $byKind;
    }

    /**
     * Bucket DTOs by `meta.group` (ungrouped bucket first via ksort), each group
     * sorted by display name with a deterministic name tie-breaker so equal
     * display names never depend on iteration order.
     *
     * @param list<ModuleAdminDto> $items
     *
     * @return array<string, list<ModuleAdminDto>>
     */
    public function groups(array $items): array
    {
        $byGroup = [];

        foreach ($items as $dto) {
            $byGroup[$dto->group ?? self::UNGROUPED][] = $dto;
        }

        ksort($byGroup);

        foreach ($byGroup as $groupCode => $groupItems) {
            usort(
                $groupItems,
                static fn(ModuleAdminDto $a, ModuleAdminDto $b): int => [$a->displayName, $a->name] <=> [$b->displayName, $b->name],
            );

            $byGroup[$groupCode] = $groupItems;
        }

        return $byGroup;
    }
}
