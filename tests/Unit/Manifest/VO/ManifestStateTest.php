<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestStateTest extends TestCase
{
    #[Test]
    public function it_rejects_camel_case_installed_at_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state contains unknown key [installedAt]');

        ManifestState::fromArray(
            ['enabled' => true, 'installedAt' => '2026-05-24T00:00:00+00:00'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_rejects_camel_case_updated_at_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state contains unknown key [updatedAt]');

        ManifestState::fromArray(
            ['enabled' => true, 'updatedAt' => '2026-05-24T00:00:00+00:00'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_accepts_snake_case_date_keys(): void
    {
        $state = ManifestState::fromArray(
            [
                'enabled' => true,
                'installed_at' => '2026-05-24T00:00:00+00:00',
                'updated_at' => '2026-05-24T12:00:00+00:00',
            ],
            '/tmp/module.json',
        );

        self::assertSame('2026-05-24T00:00:00+00:00', $state->installedAt);
        self::assertSame('2026-05-24T12:00:00+00:00', $state->updatedAt);
    }
}
