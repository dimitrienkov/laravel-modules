<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestValueObjectsTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $valueObjects = [
        'Module',
        'ManifestMeta',
        'ManifestState',
        'ModuleDependencies',
        'FeatureSchema',
        'FeatureDefinition',
        'FeatureValues',
    ];

    #[Test]
    public function manifest_value_objects_are_final_readonly_classes(): void
    {
        foreach ($this->valueObjects as $className) {
            $path = __DIR__ . "/../../src/Manifest/VO/{$className}.php";
            $contents = (string) file_get_contents($path);

            self::assertStringContainsString(
                'final readonly class ' . $className,
                $contents,
                "Manifest value object [{$className}] must be final readonly.",
            );
        }
    }
}
