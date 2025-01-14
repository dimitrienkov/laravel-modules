<?php

namespace DimitrienkoV\LaravelModules\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class ArchitectureTest extends TestCase
{
    private array $disallowedFunctions = [
        'dd(',
        'dump(',
        'var_dump(',
        'print_r(',
        'exit(',
        'die('
    ];

    public function testNoDebugAndTerminationFunctionsInCode(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());

            foreach ($this->disallowedFunctions as $function) {
                $this->assertStringNotContainsString(
                    $function, $content, "Found '{$function}' in file: {$file->getRealPath()}"
                );
            }
        }
    }

    public function testStrictTypesDeclaration(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());

            $this->assertStringContainsString(
                'declare(strict_types=1);',
                $content,
                "Missing 'declare(strict_types=1);' in file: {$file->getRealPath()}"
            );
        }
    }

    public function testEnumsInEnumDirectory(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src/Enums')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());

            $this->assertStringContainsString(
                'enum ',
                $content,
                "File {$file->getRealPath()} does not define an enum."
            );
        }
    }

    public function testReadonlyClassesInDTOsDirectory(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src/DTOs')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());
            $this->assertStringContainsString(
                'readonly class',
                $content,
                "File {$file->getRealPath()} does not define a readonly class."
            );
        }
    }

    public function testAllProvidersExtendServiceProvider(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src/Providers')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());

            $this->assertStringContainsString(
                'extends ServiceProvider',
                $content,
                "Class in file {$file->getRealPath()} does not extend ServiceProvider."
            );
        }
    }

    public function testClassesInServicesHaveServicePostfix(): void
    {
        $finder = new Finder();
        $files = $finder->in(__DIR__ . '/../src/Services')->name('*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());

            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                $this->assertStringEndsWith(
                    'Service',
                    $className,
                    "Class in file {$file->getRealPath()} does not have 'Service' postfix. Found class: {$className}"
                );
            }
        }
    }
}
