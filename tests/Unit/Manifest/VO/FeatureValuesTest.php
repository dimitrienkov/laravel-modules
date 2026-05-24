<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureValuesTest extends TestCase
{
    private FeatureSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
            'posts_per_page' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
            'api_key' => ['type' => 'string'],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_includes_module_name_in_unknown_value_error(): void
    {
        $schema = FeatureSchema::fromArray([], '/tmp/module.json');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('for module [blog]');

        FeatureValues::fromArray(
            ['nonexistent' => true],
            $schema,
            'blog',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function get_returns_explicit_value_when_set(): void
    {
        $values = FeatureValues::fromArray(
            ['comments_enabled' => false],
            $this->schema,
            'blog',
            '/tmp/module.json',
        );

        self::assertFalse($values->get('blog', 'comments_enabled'));
    }

    #[Test]
    public function get_returns_schema_default_when_value_not_set(): void
    {
        $values = FeatureValues::fromArray([], $this->schema, 'blog', '/tmp/module.json');

        self::assertTrue($values->get('blog', 'comments_enabled'));
        self::assertSame(10, $values->get('blog', 'posts_per_page'));
    }

    #[Test]
    public function get_throws_feature_not_found_for_key_without_default(): void
    {
        $values = FeatureValues::fromArray([], $this->schema, 'blog', '/tmp/module.json');

        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('[api_key]');

        $values->get('blog', 'api_key');
    }

    #[Test]
    public function with_returns_new_feature_values_with_updated_value(): void
    {
        $values = FeatureValues::fromArray(
            ['comments_enabled' => true],
            $this->schema,
            'blog',
            '/tmp/module.json',
        );

        $updated = $values->with('blog', 'comments_enabled', false, '/tmp/module.json');

        self::assertTrue($values->get('blog', 'comments_enabled'));
        self::assertFalse($updated->get('blog', 'comments_enabled'));
    }

    #[Test]
    public function explicit_values_returns_only_set_values_not_defaults(): void
    {
        $values = FeatureValues::fromArray(
            ['comments_enabled' => false],
            $this->schema,
            'blog',
            '/tmp/module.json',
        );

        $explicit = $values->explicitValues();

        self::assertSame(['comments_enabled' => false], $explicit);
        self::assertArrayNotHasKey('posts_per_page', $explicit);
    }

    #[Test]
    public function from_array_normalizes_values_through_feature_definition(): void
    {
        $schema = FeatureSchema::fromArray([
            'posts_per_page' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100],
        ], '/tmp/module.json');

        $values = FeatureValues::fromArray(
            ['posts_per_page' => 50],
            $schema,
            'blog',
            '/tmp/module.json',
        );

        self::assertSame(50, $values->get('blog', 'posts_per_page'));
    }

    #[Test]
    public function from_array_rejects_non_string_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be feature names');

        FeatureValues::fromArray(
            [0 => true], // @phpstan-ignore argument.type
            $this->schema,
            'blog',
            '/tmp/module.json',
        );
    }
}
