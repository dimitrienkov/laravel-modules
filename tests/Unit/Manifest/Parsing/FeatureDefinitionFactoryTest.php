<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureDefinitionFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureDefinitionFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_bool_definition(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'enabled',
            ['type' => 'bool', 'default' => true],
            '/tmp/module.json',
        );

        self::assertSame('enabled', $definition->key);
        self::assertSame(FeatureType::Bool, $definition->type);
        self::assertTrue($definition->hasDefault);
        self::assertTrue($definition->default);
    }

    #[Test]
    public function it_creates_int_definition_with_min_max(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'per_page',
            ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
            '/tmp/module.json',
        );

        self::assertSame(FeatureType::Int, $definition->type);
        self::assertSame(1, $definition->min);
        self::assertSame(100, $definition->max);
        self::assertSame(10, $definition->default);
    }

    #[Test]
    public function it_creates_enum_definition_with_options(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'mode',
            ['type' => 'enum', 'default' => 'auto', 'options' => ['auto', 'manual', 'off']],
            '/tmp/module.json',
        );

        self::assertSame(FeatureType::Enum, $definition->type);
        self::assertSame(['auto', 'manual', 'off'], $definition->options);
        self::assertSame('auto', $definition->default);
    }

    #[Test]
    public function it_creates_string_definition(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'title',
            ['type' => 'string', 'default' => 'hello'],
            '/tmp/module.json',
        );

        self::assertSame(FeatureType::String, $definition->type);
        self::assertSame('hello', $definition->default);
    }

    #[Test]
    public function it_parses_ui_metadata(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'enabled',
            ['type' => 'bool', 'label' => 'Enable', 'description' => 'Toggle', 'group' => 'General'],
            '/tmp/module.json',
        );

        self::assertSame('Enable', $definition->label);
        self::assertSame('Toggle', $definition->description);
        self::assertSame('General', $definition->group);
    }

    #[Test]
    public function it_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('type must be one of');

        FeatureDefinitionFactory::fromArray('key', ['type' => 'float'], '/tmp/module.json');
    }

    #[Test]
    public function it_throws_when_min_exceeds_max(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('min cannot exceed max');

        FeatureDefinitionFactory::fromArray(
            'key',
            ['type' => 'int', 'min' => 10, 'max' => 5],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_throws_for_bool_with_min(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('bool features cannot define min');

        FeatureDefinitionFactory::fromArray(
            'key',
            ['type' => 'bool', 'min' => 1],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_throws_for_enum_without_options(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('non-empty string list for enum');

        FeatureDefinitionFactory::fromArray('key', ['type' => 'enum'], '/tmp/module.json');
    }

    #[Test]
    public function it_throws_for_unknown_definition_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('unknown key [invalid]');

        FeatureDefinitionFactory::fromArray(
            'key',
            ['type' => 'bool', 'invalid' => true],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_throws_for_empty_feature_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('non-empty strings');

        FeatureDefinitionFactory::fromArray('', ['type' => 'bool'], '/tmp/module.json');
    }
}
