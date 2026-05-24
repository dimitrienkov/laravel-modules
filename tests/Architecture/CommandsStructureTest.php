<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class CommandsStructureTest extends TestCase
{
    #[Test]
    public function modules_commands_reside_in_modules_subdirectory(): void
    {
        $rootDir = __DIR__ . '/../../src/Console/Commands';

        if (! is_dir($rootDir)) {
            self::markTestSkipped('Console/Commands directory does not exist yet.');
        }

        $files = Finder::create()
            ->files()
            ->in($rootDir)
            ->depth(0)
            ->name('Modules*.php');

        foreach ($files as $file) {
            self::fail(
                "Command [{$file->getBasename()}] must reside in Console/Commands/Modules/, not in the root Console/Commands/ directory.",
            );
        }
    }
}
