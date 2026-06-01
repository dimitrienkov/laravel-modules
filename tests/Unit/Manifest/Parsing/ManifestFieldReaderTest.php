<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestFieldReader::class)]
#[Group('manifest')]
final class ManifestFieldReaderTest extends TestCase
{
    #[Test]
    public function assertAllowedKeysPassesForValidKeys(): void
    {
        ManifestFieldReader::assertAllowedKeys(
            ['name' => 'blog', 'version' => '1.0'],
            ['name' => true, 'version' => true, 'author' => true],
            'meta',
            '/tmp/module.json',
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assertAllowedKeysThrowsForUnknownKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta contains unknown key [invalid]');

        ManifestFieldReader::assertAllowedKeys(
            ['name' => 'blog', 'invalid' => true],
            ['name' => true],
            'meta',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredObjectReturnsAssociativeArray(): void
    {
        $result = ManifestFieldReader::requiredObject(
            ['meta' => ['name' => 'blog']],
            'meta',
            '/tmp/module.json',
        );

        self::assertSame(['name' => 'blog'], $result);
    }

    #[Test]
    public function requiredObjectAcceptsEmptyObject(): void
    {
        $result = ManifestFieldReader::requiredObject(
            ['meta' => []],
            'meta',
            '/tmp/module.json',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function requiredObjectThrowsForList(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta must be an object');

        ManifestFieldReader::requiredObject(
            ['meta' => ['a', 'b']],
            'meta',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredStringReturnsValue(): void
    {
        $result = ManifestFieldReader::requiredString(
            ['name' => 'blog'],
            'name',
            'meta',
            '/tmp/module.json',
        );

        self::assertSame('blog', $result);
    }

    #[Test]
    public function requiredStringThrowsForEmpty(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be a non-empty string');

        ManifestFieldReader::requiredString(
            ['name' => ''],
            'name',
            'meta',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function optionalStringReturnsNullWhenAbsent(): void
    {
        $result = ManifestFieldReader::optionalString(
            [],
            'author',
            'meta',
            '/tmp/module.json',
        );

        self::assertNull($result);
    }

    #[Test]
    public function optionalStringThrowsForNonString(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.author must be a string');

        ManifestFieldReader::optionalString(
            ['author' => 42],
            'author',
            'meta',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredBoolReturnsValue(): void
    {
        self::assertTrue(ManifestFieldReader::requiredBool(
            ['enabled' => true],
            'enabled',
            'state',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function requiredBoolThrowsForNonBool(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state.enabled must be a boolean');

        ManifestFieldReader::requiredBool(
            ['enabled' => 1],
            'enabled',
            'state',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredIntReturnsValue(): void
    {
        self::assertSame(1, ManifestFieldReader::requiredInt(
            ['schema_version' => 1],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function requiredIntThrowsForMissingKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        ManifestFieldReader::requiredInt(
            [],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredIntThrowsForStringValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        ManifestFieldReader::requiredInt(
            ['schema_version' => '1'],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredIntThrowsForFloatValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        ManifestFieldReader::requiredInt(
            ['schema_version' => 1.0],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function requiredIntThrowsForNullValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        ManifestFieldReader::requiredInt(
            ['schema_version' => null],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function optionalIntReturnsNullWhenAbsent(): void
    {
        self::assertNull(ManifestFieldReader::optionalInt(
            [],
            'min',
            'settings.schema.key',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function optionalIntThrowsForNonInt(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings.schema.key.min must be an integer');

        ManifestFieldReader::optionalInt(
            ['min' => '5'],
            'min',
            'settings.schema.key',
            '/tmp/module.json',
        );
    }

    #[Test]
    public function assertModuleNamePassesForValidNames(): void
    {
        ManifestFieldReader::assertModuleName('blog', 'meta.name', '/tmp/module.json');
        ManifestFieldReader::assertModuleName('user_auth', 'meta.name', '/tmp/module.json');
        ManifestFieldReader::assertModuleName('a1', 'meta.name', '/tmp/module.json');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assertModuleNameThrowsForInvalidNames(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be lowercase snake_case');

        ManifestFieldReader::assertModuleName('Blog', 'meta.name', '/tmp/module.json');
    }

    #[Test]
    public function assertModuleNameRejectsLeadingDigit(): void
    {
        $this->expectException(InvalidManifestException::class);

        ManifestFieldReader::assertModuleName('1blog', 'meta.name', '/tmp/module.json');
    }

    #[Test]
    public function assertModuleGroupPassesForValidGroupsAndNull(): void
    {
        ManifestFieldReader::assertModuleGroup('blog-tools', 'meta.group', '/tmp/module.json');
        ManifestFieldReader::assertModuleGroup('a1', 'meta.group', '/tmp/module.json');
        ManifestFieldReader::assertModuleGroup(null, 'meta.group', '/tmp/module.json');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assertModuleGroupThrowsForWhitespace(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.group [Bad Group] must be kebab-case');

        ManifestFieldReader::assertModuleGroup('Bad Group', 'meta.group', '/tmp/module.json');
    }

    #[Test]
    public function assertModuleGroupRejectsSnakeCase(): void
    {
        $this->expectException(InvalidManifestException::class);

        ManifestFieldReader::assertModuleGroup('my_group', 'meta.group', '/tmp/module.json');
    }
}
