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
            'meta' => [
                'name' => 'blog',
                'version' => '1.0.0',
                'dependencies' => [],
            ],
            'state' => [
                'enabled' => true,
            ],
            'settings' => [
                'schema' => [
                    'comments_enabled' => [
                        'type' => 'bool',
                        'default' => true,
                    ],
                ],
                'values' => [
                    'comments_enabled' => false,
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
            'meta' => ['name' => 'blog', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => true],
            'settings' => ['schema' => [], 'values' => []],
            'autoload' => ['psr-4' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_rejects_manifest_with_unknown_top_level_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('unknown top-level key [plugins]');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => true],
            'settings' => ['schema' => [], 'values' => []],
            'plugins' => [],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_rejects_settings_schema_as_non_object(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings.schema must be an object');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => true],
            'settings' => ['schema' => 'not-an-object', 'values' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_rejects_settings_values_as_non_object(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings.values must be an object');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => true],
            'settings' => ['schema' => [], 'values' => 'not-an-object'],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_propagates_meta_validation_errors(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be a non-empty string');

        $this->validator->validate([
            'meta' => ['name' => '', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => true],
            'settings' => ['schema' => [], 'values' => []],
        ], '/tmp/module.json');
    }

    #[Test]
    public function it_propagates_state_validation_errors(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('state.enabled must be a boolean');

        $this->validator->validate([
            'meta' => ['name' => 'blog', 'version' => '1.0.0', 'dependencies' => []],
            'state' => ['enabled' => 'yes'],
            'settings' => ['schema' => [], 'values' => []],
        ], '/tmp/module.json');
    }
}
