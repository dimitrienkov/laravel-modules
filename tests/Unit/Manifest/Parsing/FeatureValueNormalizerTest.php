<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureValueNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureValueNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_bool(): void
    {
        self::assertTrue(FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Bool,
            true,
            null,
            null,
            [],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function it_rejects_non_bool_for_bool_type(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be a boolean');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Bool,
            1,
            null,
            null,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function it_normalizes_int_within_range(): void
    {
        self::assertSame(5, FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Int,
            5,
            1,
            10,
            [],
            '/tmp/m.json',
            'value',
        ));
    }

    #[Test]
    public function it_rejects_int_below_min(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('greater than or equal to 5');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Int,
            3,
            5,
            10,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function it_rejects_int_above_max(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('less than or equal to 10');

        FeatureValueNormalizer::normalize(
            'key',
            FeatureType::Int,
            15,
            1,
            10,
            [],
            '/tmp/m.json',
            'value',
        );
    }

    #[Test]
    public function it_normalizes_string(): void
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
    public function it_normalizes_enum_value(): void
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
    public function it_rejects_invalid_enum_value(): void
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
    public function it_measures_string_length_in_characters(): void
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
