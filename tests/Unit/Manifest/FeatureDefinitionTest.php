<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\FeatureDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureDefinitionTest extends TestCase
{
    #[Test]
    public function it_measures_string_length_in_characters_not_bytes(): void
    {
        $definition = FeatureDefinition::fromArray(
            'title',
            ['type' => 'string', 'min' => 1, 'max' => 5],
            '/tmp/module.json',
        );

        $normalized = $definition->normalize("\u{1F525}\u{1F525}", '/tmp/module.json');

        self::assertSame("\u{1F525}\u{1F525}", $normalized);
    }

    #[Test]
    public function it_enforces_max_by_character_count_not_bytes(): void
    {
        $definition = FeatureDefinition::fromArray(
            'title',
            ['type' => 'string', 'max' => 2],
            '/tmp/module.json',
        );

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('less than or equal to 2');

        $definition->normalize("\u{1F525}\u{1F525}\u{1F525}", '/tmp/module.json');
    }

    #[Test]
    public function it_enforces_min_by_character_count_not_bytes(): void
    {
        $definition = FeatureDefinition::fromArray(
            'title',
            ['type' => 'string', 'min' => 3],
            '/tmp/module.json',
        );

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('greater than or equal to 3');

        $definition->normalize("\u{1F525}\u{1F525}", '/tmp/module.json');
    }
}
