<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checksum::class)]
#[Group('manifest')]
final class ChecksumTest extends TestCase
{
    private const string VALID_HEX = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    #[Test]
    public function acceptsValidLowercaseSha256Hex(): void
    {
        $checksum = new Checksum(self::VALID_HEX);

        self::assertSame(self::VALID_HEX, $checksum->value);
    }

    #[Test]
    public function algorithmIsSha256Constant(): void
    {
        self::assertSame('sha256', Checksum::ALGORITHM);
    }

    #[Test]
    #[DataProvider('invalidValueProvider')]
    public function rejectsInvalidDigest(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Checksum($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => [str_repeat('a', 63)],
            'too long' => [str_repeat('a', 65)],
            'uppercase' => [strtoupper(self::VALID_HEX)],
            'non-hex chars' => [str_repeat('g', 64)],
            'prefixed' => ['sha256:' . self::VALID_HEX],
        ];
    }
}
