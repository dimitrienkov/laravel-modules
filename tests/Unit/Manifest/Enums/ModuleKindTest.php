<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Enums;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleKind::class)]
#[Group('manifest')]
final class ModuleKindTest extends TestCase
{
    #[Test]
    public function hasThreeCases(): void
    {
        self::assertCount(3, ModuleKind::cases());
    }

    #[Test]
    public function createsFromValidStringValues(): void
    {
        self::assertSame(ModuleKind::Module, ModuleKind::from('module'));
        self::assertSame(ModuleKind::Subsystem, ModuleKind::from('subsystem'));
        self::assertSame(ModuleKind::Integration, ModuleKind::from('integration'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ModuleKind::tryFrom('invalid'));
        self::assertNull(ModuleKind::tryFrom(''));
        self::assertNull(ModuleKind::tryFrom('MODULE'));
    }

    #[Test]
    public function valuePropertyReturnsString(): void
    {
        self::assertSame('module', ModuleKind::Module->value);
        self::assertSame('subsystem', ModuleKind::Subsystem->value);
        self::assertSame('integration', ModuleKind::Integration->value);
    }
}
