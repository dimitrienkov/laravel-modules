<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureSchema::class)]
#[Group('manifest')]
final class FeatureSchemaTest extends TestCase
{
    #[Test]
    public function createsDefinitionsFromValidSchema(): void
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
    public function hasReturnsTrueForExistingKeyAndFalseForMissing(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
        ], '/tmp/module.json');

        self::assertTrue($schema->has('comments_enabled'));
        self::assertFalse($schema->has('nonexistent'));
    }

    #[Test]
    public function definitionReturnsFeatureDefinitionForExistingKey(): void
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
    public function definitionThrowsFeatureNotFoundForMissingKey(): void
    {
        $schema = FeatureSchema::fromArray([
            'comments_enabled' => ['type' => 'bool', 'default' => true],
        ], '/tmp/module.json');

        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('[missing]');

        $schema->definition('blog', 'missing');
    }

    #[Test]
    public function defaultsReturnsOnlyDefinitionsWithDefault(): void
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
    public function toArrayRoundTripPreservesStructure(): void
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
    public function fromArrayRejectsNonStringKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be feature names');

        FeatureSchema::fromArray([0 => ['type' => 'bool']], '/tmp/module.json'); // @phpstan-ignore argument.type
    }

    #[Test]
    public function fromArrayRejectsNonObjectDefinitionValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be an object');

        FeatureSchema::fromArray(['key' => 'not-an-object'], '/tmp/module.json');
    }

    #[Test]
    public function emptySchemaCreatesEmptyFeatureSchema(): void
    {
        $schema = FeatureSchema::fromArray([], '/tmp/module.json');

        self::assertSame([], $schema->all());
        self::assertSame([], $schema->defaults());
        self::assertSame([], $schema->toArray());
    }
}
