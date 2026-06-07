<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleIndexGrouping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The index ordering is a pure function of the DTO list, so it is asserted here
 * without booting the page or resource.
 */
#[CoversClass(ModuleIndexGrouping::class)]
#[Group('moonshine')]
final class ModuleIndexGroupingTest extends TestCase
{
    #[Test]
    public function bucketsByKindPreservingInputOrder(): void
    {
        $grouping = new ModuleIndexGrouping();

        $byKind = $grouping->byKind([
            $this->dto('core', 'Core', ModuleKind::Subsystem),
            $this->dto('shop', 'Shop', ModuleKind::Module),
            $this->dto('blog', 'Blog', ModuleKind::Module),
        ]);

        self::assertSame(['subsystem', 'module'], array_keys($byKind));
        self::assertSame(['Shop', 'Blog'], array_map(static fn(ModuleAdminDto $dto): string => $dto->displayName, $byKind['module']));
    }

    #[Test]
    public function ordersGroupsWithUngroupedFirstAndRowsByDisplayName(): void
    {
        $grouping = new ModuleIndexGrouping();

        $groups = $grouping->groups([
            $this->dto('zebra', 'Zebra', ModuleKind::Module, 'commerce'),
            $this->dto('loose', 'Loose', ModuleKind::Module),
            $this->dto('alpha', 'Alpha', ModuleKind::Module, 'commerce'),
        ]);

        // ksort puts the ungrouped ('') bucket before 'commerce'.
        self::assertSame(['', 'commerce'], array_keys($groups));
        self::assertSame(
            ['Alpha', 'Zebra'],
            array_map(static fn(ModuleAdminDto $dto): string => $dto->displayName, $groups['commerce']),
        );
    }

    #[Test]
    public function breaksDisplayNameTiesByModuleNameDeterministically(): void
    {
        $grouping = new ModuleIndexGrouping();

        // Same display name, different canonical names submitted in reverse order.
        $groups = $grouping->groups([
            $this->dto('beta', 'Same', ModuleKind::Module),
            $this->dto('alpha', 'Same', ModuleKind::Module),
        ]);

        self::assertSame(
            ['alpha', 'beta'],
            array_map(static fn(ModuleAdminDto $dto): string => $dto->name, $groups['']),
        );
    }

    private function dto(string $name, string $displayName, ModuleKind $kind, ?string $group = null): ModuleAdminDto
    {
        return new ModuleAdminDto(
            name: $name,
            displayName: $displayName,
            version: '1.0.0',
            kind: $kind->value,
            group: $group,
            enabled: true,
            namespace: 'App\\Modules\\' . ucfirst($name),
            path: '/tmp/' . $name,
            loadOrder: 0,
            dependencies: [],
            featureValues: [],
        );
    }
}
