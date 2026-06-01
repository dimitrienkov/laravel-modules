<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\VO\Version;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
#[Group('manifest')]
final class VersionTest extends TestCase
{
    #[Test]
    #[DataProvider('validVersionProvider')]
    public function acceptsValidSemanticVersions(string $value): void
    {
        $version = new Version($value);

        self::assertSame($value, $version->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validVersionProvider(): array
    {
        return [
            'patch' => ['1.0.0'],
            'minor patch' => ['1.2.3'],
            'two-segment' => ['2.0'],
            'with v prefix' => ['v1.0.0'],
            'pre-release' => ['1.0.0-beta1'],
        ];
    }

    #[Test]
    public function preservesOriginalStringWithoutNormalization(): void
    {
        // The author's string must round-trip into module.json / state.json
        // verbatim — a normalized form ('2.0.0.0') would be a regression.
        $version = new Version('2.0');

        self::assertSame('2.0', $version->value);
    }

    #[Test]
    #[DataProvider('invalidVersionProvider')]
    public function rejectsInvalidVersions(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid semantic version/');

        new Version($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidVersionProvider(): array
    {
        return [
            'empty' => [''],
            'non-numeric' => ['abc'],
            'wildcard segment' => ['1.x.0'],
            'whitespace' => ['   '],
        ];
    }

    #[Test]
    public function equalsComparesUnderlyingValue(): void
    {
        self::assertTrue((new Version('1.0.0'))->equals(new Version('1.0.0')));
        self::assertFalse((new Version('1.0.0'))->equals(new Version('2.0.0')));
    }
}
