<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureDefinitionFactory;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureDefinition::class)]
#[Group('manifest')]
final class FeatureDefinitionTest extends TestCase
{
    #[Test]
    public function measuresStringLengthInCharactersNotBytes(): void
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
    public function enforcesMaxByCharacterCountNotBytes(): void
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
    public function enforcesMinByCharacterCountNotBytes(): void
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
    public function toArrayRoundTripForBoolDefinition(): void
    {
        $input = ['type' => 'bool', 'default' => true];
        $definition = FeatureDefinitionFactory::fromArray('enabled', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function toArrayRoundTripForIntWithMinMax(): void
    {
        $input = ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100];
        $definition = FeatureDefinitionFactory::fromArray('per_page', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function toArrayRoundTripForEnumWithOptions(): void
    {
        $input = ['type' => 'enum', 'default' => 'auto', 'options' => ['auto', 'manual', 'off']];
        $definition = FeatureDefinitionFactory::fromArray('moderation', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
        self::assertSame(FeatureType::Enum, $definition->type);
    }

    #[Test]
    public function toArrayRoundTripForString(): void
    {
        $input = ['type' => 'string', 'default' => 'hello', 'min' => 1, 'max' => 255];
        $definition = FeatureDefinitionFactory::fromArray('greeting', $input, '/tmp/module.json');

        self::assertSame($input, $definition->toArray());
    }

    #[Test]
    public function toArrayIncludesLabelDescriptionGroupWhenPresent(): void
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
    public function toArrayOmitsLabelDescriptionGroupWhenNull(): void
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
