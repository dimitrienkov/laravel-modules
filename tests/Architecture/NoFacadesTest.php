<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class NoFacadesTest extends TestCase
{
    #[Test]
    public function src_does_not_use_facades_outside_allowed_integration_layers(): void
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__ . '/../../src')
            ->name('*.php');

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getRealPath());

            if (str_contains($path, '/src/Console/Commands/')) {
                continue;
            }

            if (str_contains($path, '/src/MoonShine/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getRealPath());

            self::assertStringNotContainsString(
                'Illuminate\\Support\\Facades\\',
                $contents,
                "Facade import found in [{$file->getRealPath()}].",
            );
        }
    }
}
