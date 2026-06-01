<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureValues::class)]
#[Group('manifest')]
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
    public function includesModuleNameInUnknownValueError(): void
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
    public function getReturnsExplicitValueWhenSet(): void
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
    public function getReturnsSchemaDefaultWhenValueNotSet(): void
    {
        $values = FeatureValues::fromArray([], $this->schema, 'blog', '/tmp/module.json');

        self::assertTrue($values->get('blog', 'comments_enabled'));
        self::assertSame(10, $values->get('blog', 'posts_per_page'));
    }

    #[Test]
    public function getThrowsFeatureNotFoundForKeyWithoutDefault(): void
    {
        $values = FeatureValues::fromArray([], $this->schema, 'blog', '/tmp/module.json');

        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('[api_key]');

        $values->get('blog', 'api_key');
    }

    #[Test]
    public function withReturnsNewFeatureValuesWithUpdatedValue(): void
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
    public function explicitValuesReturnsOnlySetValuesNotDefaults(): void
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
    public function fromArrayNormalizesValuesThroughFeatureDefinition(): void
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
    public function fromArrayRejectsNonStringKey(): void
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
