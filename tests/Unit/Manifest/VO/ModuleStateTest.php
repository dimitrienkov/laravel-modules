<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleStateTest extends TestCase
{
    #[Test]
    public function it_rejects_camel_case_installed_at_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state contains unknown key [installedAt]');

        ModuleState::fromArray(
            ['enabled' => true, 'installedAt' => '2026-05-24T00:00:00+00:00'],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function it_rejects_camel_case_updated_at_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state contains unknown key [updatedAt]');

        ModuleState::fromArray(
            ['enabled' => true, 'updatedAt' => '2026-05-24T00:00:00+00:00'],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function it_accepts_snake_case_date_keys(): void
    {
        $state = ModuleState::fromArray(
            [
                'enabled' => true,
                'installed_at' => '2026-05-24T00:00:00+00:00',
                'updated_at' => '2026-05-24T12:00:00+00:00',
            ],
            '/tmp/state.json',
        );

        self::assertSame('2026-05-24T00:00:00+00:00', $state->installedAt);
        self::assertSame('2026-05-24T12:00:00+00:00', $state->updatedAt);
    }

    #[Test]
    public function it_rejects_missing_enabled_field(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state.enabled must be a boolean');

        ModuleState::fromArray(
            ['installed_at' => '2026-05-24T00:00:00+00:00'],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function disabled_default_returns_disabled_state(): void
    {
        $state = ModuleState::disabledDefault();

        self::assertFalse($state->enabled);
        self::assertNull($state->installedAt);
        self::assertNull($state->updatedAt);
    }
}
