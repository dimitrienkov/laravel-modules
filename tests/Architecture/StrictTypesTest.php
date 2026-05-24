<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class StrictTypesTest extends TestCase
{
    #[Test]
    public function php_files_under_src_tests_and_stubs_declare_strict_types(): void
    {
        foreach ($this->phpFiles() as $file) {
            $contents = (string) file_get_contents($file->getRealPath());

            self::assertStringContainsString(
                'declare(strict_types=1);',
                $contents,
                "Missing strict types declaration in [{$file->getRealPath()}].",
            );
        }
    }

    private function phpFiles(): Finder
    {
        $directories = [
            __DIR__ . '/../../src',
            __DIR__ . '/..',
        ];

        if (is_dir(__DIR__ . '/../../stubs')) {
            $directories[] = __DIR__ . '/../../stubs';
        }

        return Finder::create()
            ->files()
            ->in($directories)
            ->name('*.php')
            ->notPath('Fixtures');
    }
}
