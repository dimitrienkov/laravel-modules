<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestValidator::class)]
#[Group('manifest')]
final class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ManifestValidator(new ManifestSettingsValidator());
    }

    #[Test]
    public function validatesValidManifestSuccessfully(): void
    {
        $this->validator->validate([
            'schema_version' => 1,
            'meta' => [
                'name' => 'blog',
                'kind' => 'module',
                'version' => '1.0.0',
                'dependencies' => [],
            ],
            'settings' => [
                'schema' => [
                    'comments_enabled' => [
                        'type' => 'bool',
                        'default' => true,
                    ],
                ],
            ],
        ], '/tmp/module.json');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejectsManifestWithAutoloadSection(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('autoload section is not supported');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
            'autoload' => ['psr-4' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsManifestWithUnknownTopLevelKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('unknown top-level key [plugins]');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
            'plugins' => [],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsManifestWithStateSection(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('unknown top-level key [state]');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
            'state' => ['enabled' => true],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsSettingsSchemaAsNonObject(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings.schema must be an object');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => 'not-an-object'],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsSettingsValuesKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings contains unknown key [values]');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => [], 'values' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsManifestWithoutSchemaVersion(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsStringSchemaVersion(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        $this->validator->validate([
            'schema_version' => '1',
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function rejectsUnsupportedSchemaVersion(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('schema_version 2 is not supported; expected 1');

        $this->validator->validate([
            'schema_version' => 2,
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function propagatesMetaValidationErrors(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be a non-empty string');

        $this->validator->validate([
            'schema_version' => 1,
            'meta' => ['name' => '', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
        ], '/tmp/module.json');
    }
}
