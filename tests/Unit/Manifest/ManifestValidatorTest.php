<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ManifestValidator(new ManifestSettingsValidator());
    }

    #[Test]
    public function it_validates_valid_manifest_successfully(): void
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
    public function it_rejects_manifest_with_autoload_section(): void
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
    public function it_rejects_manifest_with_unknown_top_level_key(): void
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
    public function it_rejects_manifest_with_state_section(): void
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
    public function it_rejects_settings_schema_as_non_object(): void
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
    public function it_rejects_settings_values_key(): void
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
    public function it_rejects_manifest_without_schema_version(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('manifest.schema_version must be an integer');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'dependencies' => []],
            'settings' => ['schema' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_rejects_string_schema_version(): void
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
    public function it_rejects_unsupported_schema_version(): void
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
    public function it_propagates_meta_validation_errors(): void
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
