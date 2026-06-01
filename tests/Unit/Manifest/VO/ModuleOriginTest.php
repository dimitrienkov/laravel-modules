<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleOriginKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleOrigin::class)]
#[Group('manifest')]
final class ModuleOriginTest extends TestCase
{
    private const string VALID_HEX = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

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
        $origin = ModuleOrigin::forZip('2.0.0', new Checksum(self::VALID_HEX));

        self::assertSame(ModuleOriginKind::Zip, $origin->kind);
        self::assertSame('2.0.0', $origin->installedVersion);
        self::assertInstanceOf(Checksum::class, $origin->checksum);
        self::assertSame(self::VALID_HEX, $origin->checksum->value);
    }

    #[Test]
    public function constructorRejectsZipWithoutChecksum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleOrigin(ModuleOriginKind::Zip, '1.0.0', null);
    }

    #[Test]
    public function constructorRejectsLocalWithChecksum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleOrigin(ModuleOriginKind::Local, '1.0.0', new Checksum(self::VALID_HEX));
    }

    #[Test]
    public function toArrayProducesDeterministicOrder(): void
    {
        $origin = ModuleOrigin::forZip('1.0.0', new Checksum(self::VALID_HEX));

        $array = $origin->toArray();

        self::assertSame(['kind', 'installed_version', 'checksum'], array_keys($array));
        self::assertSame('zip', $array['kind']);
        self::assertSame('1.0.0', $array['installed_version']);
        self::assertSame(self::VALID_HEX, $array['checksum']);
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
    public function fromArrayRoundTripZip(): void
    {
        $original = ModuleOrigin::forZip('3.0.0', new Checksum(self::VALID_HEX));

        $restored = ModuleOrigin::fromArray($original->toArray(), '/tmp/state.json');

        self::assertSame($original->kind, $restored->kind);
        self::assertSame($original->installedVersion, $restored->installedVersion);
        self::assertInstanceOf(Checksum::class, $restored->checksum);
        self::assertSame(self::VALID_HEX, $restored->checksum->value);
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
    public function fromArrayThrowsOnEmptyKindAsInvalidNotMissing(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/not valid/');

        ModuleOrigin::fromArray(['kind' => '', 'installed_version' => '1.0.0'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsOnMissingInstalledVersion(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/installed_version/');

        ModuleOrigin::fromArray(['kind' => 'local'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsOnWhitespaceInstalledVersion(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/installed_version/');

        ModuleOrigin::fromArray(['kind' => 'local', 'installed_version' => '   '], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsWhenZipMissingChecksum(): void
    {
        // Absent checksum parses to null; the constructor invariant rejects it
        // and fromArray re-throws it as a state error with path context.
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/requires a checksum/');

        ModuleOrigin::fromArray(['kind' => 'zip', 'installed_version' => '1.0.0'], '/tmp/state.json');
    }

    #[Test]
    public function fromArrayThrowsWhenLocalHasChecksum(): void
    {
        // Present checksum parses fine; the constructor invariant rejects a
        // checksum on a local origin and fromArray wraps it with path context.
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/must not carry a checksum/');

        ModuleOrigin::fromArray(
            ['kind' => 'local', 'installed_version' => '1.0.0', 'checksum' => self::VALID_HEX],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function fromArrayThrowsOnInvalidChecksumHex(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/Checksum must be/');

        ModuleOrigin::fromArray(
            ['kind' => 'zip', 'installed_version' => '1.0.0', 'checksum' => 'abc123'],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function fromArrayThrowsOnNonStringChecksum(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/checksum/');

        ModuleOrigin::fromArray(
            ['kind' => 'zip', 'installed_version' => '1.0.0', 'checksum' => 123],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function fromArrayThrowsOnUnknownSourceKey(): void
    {
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/unknown keys.*extra/');

        ModuleOrigin::fromArray(
            ['kind' => 'local', 'installed_version' => '1.0.0', 'extra' => 'value'],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function fromArrayTreatsExplicitNullChecksumAsPresentForLocal(): void
    {
        // array_key_exists, not isset: an explicit null is a present key, so the
        // parser rejects it as a non-string checksum before the kind invariant —
        // it never silently passes as an absent (allowed) checksum for local.
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/checksum must be a string/');

        ModuleOrigin::fromArray(
            ['kind' => 'local', 'installed_version' => '1.0.0', 'checksum' => null],
            '/tmp/state.json',
        );
    }

    #[Test]
    public function fromArrayTreatsExplicitNullChecksumAsNonStringForZip(): void
    {
        // For zip the key is present, so it must fail as a non-string checksum — not as "required".
        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/checksum must be a string/');

        ModuleOrigin::fromArray(
            ['kind' => 'zip', 'installed_version' => '1.0.0', 'checksum' => null],
            '/tmp/state.json',
        );
    }
}
