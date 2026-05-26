<?php

declare(strict_types=1);

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

arch('package classes use strict types')
    ->expect('DimitrienkoV\LaravelModules')
    ->toUseStrictTypes();

test('php files under src tests and stubs declare strict types', function (): void {
    $directories = [
        realpath(__DIR__ . '/../../src'),
        realpath(__DIR__ . '/../../tests'),
    ];

    $stubsDir = realpath(__DIR__ . '/../../stubs');
    if ($stubsDir !== false) {
        $directories[] = $stubsDir;
    }

    $directories = array_filter($directories);

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, '/Fixtures/')) {
                continue;
            }

            $contents = (string) file_get_contents($path);
            expect($contents)
                ->toContain('declare(strict_types=1)')
                ->and($path)->not->toBeEmpty();
        }
    }
});

arch('all concrete src classes are final')
    ->expect('DimitrienkoV\LaravelModules')
    ->classes()
    ->toBeFinal();

arch('value objects are final readonly')
    ->expect('DimitrienkoV\LaravelModules\Manifest\VO')
    ->classes()
    ->toBeReadonly()
    ->toBeFinal();

arch('loaders are final and implement LoaderInterface')
    ->expect('DimitrienkoV\LaravelModules\Loaders')
    ->classes()
    ->toBeFinal()
    ->toImplement(LoaderInterface::class)
    ->ignoring(ModuleLoaderPipeline::class);

test('src does not contain debug or termination calls', function (): void {
    $srcDir = realpath(__DIR__ . '/../../src');
    expect($srcDir)->not->toBeFalse();

    $forbidden = ['dd(', 'dump(', 'var_dump(', 'print_r(', 'exit(', 'die('];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        foreach ($forbidden as $call) {
            expect($contents)->not->toContain($call, "Found {$call} in {$file->getPathname()}");
        }
    }
});

arch('src does not use Laravel facades')
    ->expect('DimitrienkoV\LaravelModules')
    ->not->toUse('Illuminate\Support\Facades');

test('src does not define mutable static properties', function (): void {
    $srcDir = realpath(__DIR__ . '/../../src');
    expect($srcDir)->not->toBeFalse();

    $pattern = '/(?:public|protected|private)\s+static\s+(?!function\b)/';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        expect((bool) preg_match($pattern, $contents))
            ->toBeFalse("Mutable static property in {$file->getPathname()}");
    }
});

arch('exceptions are final')
    ->expect('DimitrienkoV\LaravelModules\Exceptions')
    ->classes()
    ->toBeFinal()
    ->toExtend(RuntimeException::class);

arch('contracts are interfaces')
    ->expect('DimitrienkoV\LaravelModules\Contracts')
    ->toBeInterfaces();

arch('providers extend Laravel ServiceProvider')
    ->expect('DimitrienkoV\LaravelModules\Providers')
    ->classes()
    ->toExtend(ServiceProvider::class);

arch('artisan commands extend Laravel Command')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands')
    ->classes()
    ->toExtend(Command::class);

arch('manifest enums are string backed enums')
    ->expect('DimitrienkoV\LaravelModules\Manifest\Enums')
    ->toBeStringBackedEnums();

arch('use cases are final readonly')
    ->expect('DimitrienkoV\LaravelModules\Application\UseCases')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('application support classes are final readonly')
    ->expect('DimitrienkoV\LaravelModules\Application\Support')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('application DTOs are final readonly')
    ->expect('DimitrienkoV\LaravelModules\Application\DTOs')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('application layer does not depend on loaders or providers')
    ->expect('DimitrienkoV\LaravelModules\Application')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Loaders',
        'DimitrienkoV\LaravelModules\MoonShine',
    ]);

arch('ZipExtractor is final readonly')
    ->expect('DimitrienkoV\LaravelModules\Support\ZipExtractor')
    ->toBeFinal()
    ->toBeReadonly();

arch('application enums are string backed enums')
    ->expect('DimitrienkoV\LaravelModules\Application\Enums')
    ->toBeStringBackedEnums();

test('direct filesystem I/O is only allowed in specialized infrastructure classes', function (): void {
    $srcDir = realpath(__DIR__ . '/../../src');
    expect($srcDir)->not->toBeFalse();

    $forbiddenFunctions = [
        'file_get_contents(',
        'file_put_contents(',
        'fopen(',
        'fwrite(',
        'unlink(',
        'rmdir(',
        'mkdir(',
        'copy(',
        'rename(',
    ];

    $allowedClasses = [
        'AtomicFileWriter.php',
        'AtomicJsonWriter.php',
        'ManifestDocumentReader.php',
        'ModuleStateRepository.php',
        'ModuleDirectoryOperations.php',
        'ModuleSkeletonBuilder.php',
        'TemporaryDirectoryCleaner.php',
        'ZipExtractor.php',
        'ModuleStatePaths.php',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $basename = $file->getBasename();

        if (\in_array($basename, $allowedClasses, true)) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        foreach ($forbiddenFunctions as $fn) {
            expect($contents)->not->toContain(
                $fn,
                "Direct filesystem I/O [{$fn}] found in {$file->getPathname()} — use a specialized infrastructure class instead.",
            );
        }
    }
});
