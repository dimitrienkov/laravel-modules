<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class StaticStateTest extends TestCase
{
    #[Test]
    public function src_does_not_define_mutable_static_properties(): void
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__ . '/../../src')
            ->name('*.php');

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getRealPath());

            self::assertDoesNotMatchRegularExpression(
                '/(?:public|protected|private)\s+static\s+(?!function\b)/',
                $contents,
                "Mutable static property found in [{$file->getRealPath()}].",
            );
        }
    }
}
