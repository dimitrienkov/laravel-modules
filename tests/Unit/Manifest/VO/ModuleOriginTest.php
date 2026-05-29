<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleOriginKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleOriginTest extends TestCase
{
    #[Test]
    public function forLocalCreatesLocalOrigin(): void
    {
        $origin = ModuleOrigin::forLocal('1.0.0');

        self::assertSame(ModuleOriginKind::Local, $origin->kind);
        self::assertSame('1.0.0', $origin->installedVersion);
        self::assertNull($origin->checksum);
    }

    #[Test]
    public function forZipCreatesZipOriginWithChecksum(): void
    {
        $origin = ModuleOrigin::forZip('2.0.0', 'abc123');

        self::assertSame(ModuleOriginKind::Zip, $origin->kind);
        self::assertSame('2.0.0', $origin->installedVersion);
        self::assertSame('abc123', $origin->checksum);
    }

    #[Test]
    public function toArrayProducesDeterministicOrder(): void
    {
        $origin = ModuleOrigin::forZip('1.0.0', 'sha256hash');

        $array = $origin->toArray();

        $keys = array_keys($array);
        self::assertSame(['kind', 'installed_version', 'checksum'], $keys);
        self::assertSame('zip', $array['kind']);
        self::assertSame('1.0.0', $array['installed_version']);
        self::assertSame('sha256hash', $array['checksum']);
    }

    #[Test]
    public function toArrayOmitsNullChecksum(): void
    {
        $origin = ModuleOrigin::forLocal('1.0.0');

        $array = $origin->toArray();

        self::assertArrayNotHasKey('checksum', $array);
        self::assertSame(['kind', 'installed_version'], array_keys($array));
    }

    #[Test]
    public function fromArrayRoundTrip(): void
    {
        $original = ModuleOrigin::forZip('3.0.0', 'deadbeef');

        $restored = ModuleOrigin::fromArray($original->toArray(), '/tmp/state.json');

        self::assertSame($original->kind, $restored->kind);
        self::assertSame($original->installedVersion, $restored->installedVersion);
        self::assertSame($original->checksum, $restored->checksum);
    }

    #[Test]
    public function fromArrayLocalRoundTrip(): void
    {
        $original = ModuleOrigin::forLocal('1.0.0');

        $restored = ModuleOrigin::fromArray($original->toArray(), '/tmp/state.json');

        self::assertSame(ModuleOriginKind::Local, $restored->kind);
        self::assertSame('1.0.0', $restored->installedVersion);
        self::assertNull($restored->checksum);
    }

    #[Test]
    public function fromArrayThrowsOnMissingKind(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/source\.kind/');

        ModuleOrigin::fromArray(['installed_version' => '1.0.0'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsOnInvalidKind(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/source\.kind.*not valid/');

        ModuleOrigin::fromArray(['kind' => 'git', 'installed_version' => '1.0.0'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsOnMissingInstalledVersion(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/installed_version/');

        ModuleOrigin::fromArray(['kind' => 'local'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsOnNonStringChecksum(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/checksum/');

        ModuleOrigin::fromArray(['kind' => 'zip', 'installed_version' => '1.0.0', 'checksum' => 123], '/tmp/state.json');
    }
}
