<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class ApplicationLayerTest extends TestCase
{
    #[Test]
    public function application_use_cases_do_not_depend_on_eloquent_models(): void
    {
        $directory = __DIR__ . '/../../src/Application/UseCases';

        if (! is_dir($directory)) {
            self::assertDirectoryDoesNotExist($directory);

            return;
        }

        $files = Finder::create()
            ->files()
            ->in($directory)
            ->name('*.php');

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getRealPath());

            self::assertStringNotContainsString(
                'Illuminate\\Database\\Eloquent\\Model',
                $contents,
                "UseCase [{$file->getRealPath()}] must not depend on Eloquent Model.",
            );
        }
    }
}
