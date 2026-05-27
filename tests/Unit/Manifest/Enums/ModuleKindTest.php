<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Enums;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleKindTest extends TestCase
{
    #[Test]
    public function it_has_three_cases(): void
    {
        self::assertCount(3, ModuleKind::cases());
    }

    #[Test]
    public function it_creates_from_valid_string_values(): void
    {
        self::assertSame(ModuleKind::Module, ModuleKind::from('module'));
        self::assertSame(ModuleKind::Subsystem, ModuleKind::from('subsystem'));
        self::assertSame(ModuleKind::Integration, ModuleKind::from('integration'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        self::assertNull(ModuleKind::tryFrom('invalid'));
        self::assertNull(ModuleKind::tryFrom(''));
        self::assertNull(ModuleKind::tryFrom('MODULE'));
    }

    #[Test]
    public function value_property_returns_string(): void
    {
        self::assertSame('module', ModuleKind::Module->value);
        self::assertSame('subsystem', ModuleKind::Subsystem->value);
        self::assertSame('integration', ModuleKind::Integration->value);
    }
}
