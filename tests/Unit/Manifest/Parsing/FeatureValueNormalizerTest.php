<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureValueNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureValueNormalizer::class)]
#[Group('manifest')]
final class FeatureValueNormalizerTest extends TestCase
{
    #[Test]
    public function normalizesBool(): void
    {
        self::assertTrue(FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Boolean,
            true,
            null,
            null,
            [],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function rejectsNonBoolForBoolType(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be a boolean');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Boolean,
            1,
            null,
            null,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function normalizesIntWithinRange(): void
    {
        self::assertSame(5, FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Integer,
            5,
            1,
            10,
            [],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function rejectsIntBelowMin(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('greater than or equal to 5');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Integer,
            3,
            5,
            10,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function rejectsIntAboveMax(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('less than or equal to 10');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Integer,
            15,
            1,
            10,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function normalizesString(): void
    {
        self::assertSame('hello', FeatureValueNormalizer::normalize(
            'key',
            FeatureType::String,
            'hello',
            null,
            null,
            [],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function normalizesEnumValue(): void
    {
        self::assertSame('auto', FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Enum,
            'auto',
            null,
            null,
            ['auto', 'manual'],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function rejectsInvalidEnumValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be one of: auto, manual');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Enum,
            'invalid',
            null,
            null,
            ['auto', 'manual'],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function measuresStringLengthInCharacters(): void
    {
        self::assertSame("\u{1F525}\u{1F525}", FeatureValueNormalizer::normalize(
            'key',
            FeatureType::String,
            "\u{1F525}\u{1F525}",
            1,
            5,
            [],
            '/tmp/m.json',
            'value',
        ));
    }
}
