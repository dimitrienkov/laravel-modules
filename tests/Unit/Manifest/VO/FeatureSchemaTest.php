<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureSchemaTest extends TestCase
{
    #[Test]
    public function it_creates_definitions_from_valid_schema(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
            'posts_per_page' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
        ], '/tmp/module.json');

        self::assertCount(2, $schema->all());
        self::assertTrue($schema->has('comments_enabled'));
        self::assertTrue($schema->has('posts_per_page'));
    }

    #[Test]
    public function has_returns_true_for_existing_key_and_false_for_missing(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
        ], '/tmp/module.json');

        self::assertTrue($schema->has('comments_enabled'));
        self::assertFalse($schema->has('nonexistent'));
    }

    #[Test]
    public function definition_returns_feature_definition_for_existing_key(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
        ], '/tmp/module.json');

        $definition = $schema->definition('blog', 'comments_enabled');

        self::assertSame('comments_enabled', $definition->key);
        self::assertTrue($definition->hasDefault);
        self::assertTrue($definition->default);
    }

    #[Test]
    public function definition_throws_feature_not_found_for_missing_key(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
        ], '/tmp/module.json');

        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('[missing]');

        $schema->definition('blog', 'missing');
    }

    #[Test]
    public function defaults_returns_only_definitions_with_default(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
            'posts_per_page' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
            'api_key' => ['type' => 'string'],
        ], '/tmp/module.json');

        $defaults = $schema->defaults();

        self::assertCount(2, $defaults);
        self::assertSame(true, $defaults['comments_enabled']);
        self::assertSame(10, $defaults['posts_per_page']);
        self::assertArrayNotHasKey('api_key', $defaults);
    }

    #[Test]
    public function to_array_round_trip_preserves_structure(): void
    {
        $input = [
            'comments_enabled' => ['type' => 'bool', 'default' => true],
            'posts_per_page' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
            'moderation' => ['type' => 'enum', 'default' => 'auto', 'options' => ['auto', 'manual', 'off']],
        ];

        $schema = FeatureSchema::fromArray($input, '/tmp/module.json');
        $output = $schema->toArray();

        self::assertSame($input['comments_enabled'], $output['comments_enabled']);
        self::assertSame($input['posts_per_page'], $output['posts_per_page']);
        self::assertSame($input['moderation'], $output['moderation']);
    }

    #[Test]
    public function from_array_rejects_non_string_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be feature names');

        FeatureSchema::fromArray([0 => ['type' => 'bool']], '/tmp/module.json'); // @phpstan-ignore argument.type
    }

    #[Test]
    public function from_array_rejects_non_object_definition_value(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be an object');

        FeatureSchema::fromArray(['key' => 'not-an-object'], '/tmp/module.json');
    }

    #[Test]
    public function empty_schema_creates_empty_feature_schema(): void
    {
        $schema = FeatureSchema::fromArray([], '/tmp/module.json');

        self::assertSame([], $schema->all());
        self::assertSame([], $schema->defaults());
        self::assertSame([], $schema->toArray());
    }
}
