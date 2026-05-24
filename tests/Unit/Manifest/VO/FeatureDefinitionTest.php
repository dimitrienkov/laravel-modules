<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureDefinitionFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureDefinitionTest extends TestCase
{
    #[Test]
    public function it_measures_string_length_in_characters_not_bytes(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
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
        $definition = FeatureDefinitionFactory::fromArray(
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
        $definition = FeatureDefinitionFactory::fromArray(
            'title',
            ['type' => 'string', 'min' => 3],
            '/tmp/module.json',
        );

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('greater than or equal to 3');

        $definition->normalize("\u{1F525}\u{1F525}", '/tmp/module.json');
    }

    #[Test]
    public function to_array_round_trip_for_bool_definition(): void
    {
        $input = ['type' => 'bool', 'default' => true];
        $definition = FeatureDefinitionFactory::fromArray('enabled', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function to_array_round_trip_for_int_with_min_max(): void
    {
        $input = ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100];
        $definition = FeatureDefinitionFactory::fromArray('per_page', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function to_array_round_trip_for_enum_with_options(): void
    {
        $input = ['type' => 'enum', 'default' => 'auto', 'options' => ['auto', 'manual', 'off']];
        $definition = FeatureDefinitionFactory::fromArray('moderation', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
        self::assertSame(FeatureType::Enum, $definition->type);
    }

    #[Test]
    public function to_array_round_trip_for_string(): void
    {
        $input = ['type' => 'string', 'default' => 'hello', 'min' => 1, 'max' => 255];
        $definition = FeatureDefinitionFactory::fromArray('greeting', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function to_array_includes_label_description_group_when_present(): void
    {
        $input = [
            'type' => 'bool',
            'default' => true,
            'label' => 'Enable comments',
            'description' => 'Allows users to post comments',
            'group' => 'Features',
        ];
        $definition = FeatureDefinitionFactory::fromArray('comments', $input, '/tmp/module.json');

        $output = $definition->toArray();

        self::assertSame('Enable comments', $output['label']);
        self::assertSame('Allows users to post comments', $output['description']);
        self::assertSame('Features', $output['group']);
    }

    #[Test]
    public function to_array_omits_label_description_group_when_null(): void
    {
        $definition = FeatureDefinitionFactory::fromArray(
            'enabled',
            ['type' => 'bool', 'default' => true],
            '/tmp/module.json',
        );

        $output = $definition->toArray();

        self::assertArrayNotHasKey('label', $output);
        self::assertArrayNotHasKey('description', $output);
        self::assertArrayNotHasKey('group', $output);
    }
}
