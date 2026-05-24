<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureValuesTest extends TestCase
{
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
}
