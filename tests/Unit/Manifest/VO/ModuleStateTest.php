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
        $state = ModuleState::defaultDisabled();

        self::assertFalse($state->enabled);
        self::assertNull($state->installedAt);
        self::assertNull($state->updatedAt);
    }

    #[Test]
    public function initialStateReturnsEnabledWithTimestamps(): void
    {
        $before = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $state = ModuleState::initialState();
        $after = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        self::assertTrue($state->enabled);
        self::assertNotNull($state->installedAt);
        self::assertNotNull($state->updatedAt);
        self::assertSame($state->installedAt, $state->updatedAt);
        self::assertGreaterThanOrEqual($before, $state->installedAt);
        self::assertLessThanOrEqual($after, $state->installedAt);
    }

    #[Test]
    public function initialStateRespectsDisabledFlag(): void
    {
        $state = ModuleState::initialState(enabled: false);

        self::assertFalse($state->enabled);
        self::assertNotNull($state->installedAt);
    }

    #[Test]
    public function updatedFromPreservesEnabledAndInstalledAt(): void
    {
        $original = new ModuleState(
            enabled: true,
            installedAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-01-01T00:00:00+00:00',
        );

        $updated = ModuleState::updatedFrom($original);

        self::assertTrue($updated->enabled);
        self::assertSame('2025-01-01T00:00:00+00:00', $updated->installedAt);
        self::assertNotSame('2025-01-01T00:00:00+00:00', $updated->updatedAt);
        self::assertNotNull($updated->updatedAt);
    }

    #[Test]
    public function withEnabledTogglesEnabled(): void
    {
        $disabled = new ModuleState(
            enabled: false,
            installedAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-06-01T00:00:00+00:00',
        );

        $enabled = $disabled->withEnabled(true);

        self::assertTrue($enabled->enabled);
        self::assertSame($disabled->installedAt, $enabled->installedAt);
        self::assertSame($disabled->updatedAt, $enabled->updatedAt);
    }

    #[Test]
    public function toArrayOmitsNullTimestamps(): void
    {
        $state = ModuleState::defaultDisabled();
        $array = $state->toArray();

        self::assertArrayHasKey('enabled', $array);
        self::assertArrayNotHasKey('installed_at', $array);
        self::assertArrayNotHasKey('updated_at', $array);
    }

    #[Test]
    public function toArrayIncludesTimestampsWhenPresent(): void
    {
        $state = ModuleState::initialState();
        $array = $state->toArray();

        self::assertArrayHasKey('enabled', $array);
        self::assertArrayHasKey('installed_at', $array);
        self::assertArrayHasKey('updated_at', $array);
    }
}
