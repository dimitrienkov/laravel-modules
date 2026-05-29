<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestFieldReaderTest extends TestCase
{
    #[Test]
    public function assert_allowed_keys_passes_for_valid_keys(): void
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
    public function assert_allowed_keys_throws_for_unknown_key(): void
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
    public function required_object_returns_associative_array(): void
    {
        $result = ManifestFieldReader::requiredObject(
            ['meta' => ['name' => 'blog']],
            'meta',
            '/tmp/module.json',
        );

        self::assertSame(['name' => 'blog'], $result);
    }

    #[Test]
    public function required_object_accepts_empty_object(): void
    {
        $result = ManifestFieldReader::requiredObject(
            ['meta' => []],
            'meta',
            '/tmp/module.json',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function required_object_throws_for_list(): void
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
    public function required_string_returns_value(): void
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
    public function required_string_throws_for_empty(): void
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
    public function optional_string_returns_null_when_absent(): void
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
    public function optional_string_throws_for_non_string(): void
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
    public function required_bool_returns_value(): void
    {
        self::assertTrue(ManifestFieldReader::requiredBool(
            ['enabled' => true],
            'enabled',
            'state',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function required_bool_throws_for_non_bool(): void
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
    public function required_int_returns_value(): void
    {
        self::assertSame(1, ManifestFieldReader::requiredInt(
            ['schema_version' => 1],
            'schema_version',
            'manifest',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function required_int_throws_for_missing_key(): void
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
    public function required_int_throws_for_string_value(): void
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
    public function required_int_throws_for_float_value(): void
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
    public function required_int_throws_for_null_value(): void
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
    public function optional_int_returns_null_when_absent(): void
    {
        self::assertNull(ManifestFieldReader::optionalInt(
            [],
            'min',
            'settings.schema.key',
            '/tmp/module.json',
        ));
    }

    #[Test]
    public function optional_int_throws_for_non_int(): void
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
    public function assert_module_name_passes_for_valid_names(): void
    {
        ManifestFieldReader::assertModuleName('blog', 'meta.name', '/tmp/module.json');
        ManifestFieldReader::assertModuleName('user_auth', 'meta.name', '/tmp/module.json');
        ManifestFieldReader::assertModuleName('a1', 'meta.name', '/tmp/module.json');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assert_module_name_throws_for_invalid_names(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be lowercase snake_case');

        ManifestFieldReader::assertModuleName('Blog', 'meta.name', '/tmp/module.json');
    }

    #[Test]
    public function assert_module_name_rejects_leading_digit(): void
    {
        $this->expectException(InvalidManifestException::class);

        ManifestFieldReader::assertModuleName('1blog', 'meta.name', '/tmp/module.json');
    }

    #[Test]
    public function assert_module_group_passes_for_valid_groups_and_null(): void
    {
        ManifestFieldReader::assertModuleGroup('blog-tools', 'meta.group', '/tmp/module.json');
        ManifestFieldReader::assertModuleGroup('a1', 'meta.group', '/tmp/module.json');
        ManifestFieldReader::assertModuleGroup(null, 'meta.group', '/tmp/module.json');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assert_module_group_throws_for_whitespace(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.group [Bad Group] must be kebab-case');

        ManifestFieldReader::assertModuleGroup('Bad Group', 'meta.group', '/tmp/module.json');
    }

    #[Test]
    public function assert_module_group_rejects_snake_case(): void
    {
        $this->expectException(InvalidManifestException::class);

        ManifestFieldReader::assertModuleGroup('my_group', 'meta.group', '/tmp/module.json');
    }
}
