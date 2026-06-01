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

arch('loaders do not depend on ModuleKind')
    ->expect('DimitrienkoV\LaravelModules\Loaders')
    ->not->toUse('DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind');

arch('application layer does not depend on loaders or moonshine')
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

arch('optimize commands do not depend on concrete registry or cache classes')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeCommand')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Manifest\ModuleRegistry',
        'DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache',
    ]);

arch('optimize clear command does not depend on concrete registry or cache classes')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeClearCommand')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Manifest\ModuleRegistry',
        'DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache',
    ]);

arch('list command does not depend on concrete registry')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesListCommand')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface',
        'DimitrienkoV\LaravelModules\Manifest\ModuleRegistry',
    ]);

arch('lifecycle commands do not depend on concrete infrastructure classes')
    ->expect([
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand',
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesInstallCommand',
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesUpdateCommand',
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand',
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesEnableCommand',
        'DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand',
    ])
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Manifest\ModuleRegistry',
        'DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache',
        'DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository',
        'DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository',
        'DimitrienkoV\LaravelModules\Support\LocalFilesystem',
        'DimitrienkoV\LaravelModules\Support\AtomicFileWriter',
        'DimitrienkoV\LaravelModules\Support\AtomicJsonWriter',
    ]);

arch('exceptions implement ModuleExceptionInterface')
    ->expect('DimitrienkoV\LaravelModules\Exceptions')
    ->classes()
    ->toImplement('DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface');

arch('application layer does not depend on providers')
    ->expect('DimitrienkoV\LaravelModules\Application')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Providers',
    ]);

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
        'is_file(',
        'is_dir(',
        'file_exists(',
    ];

    $allowedClasses = [
        'AtomicFileWriter.php',
        'ManifestDocumentReader.php',
        'LocalFilesystem.php',
        'ModuleRegistryCache.php',
        'FactoryLoader.php',
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

arch('use cases use the UseCase suffix')
    ->expect('DimitrienkoV\LaravelModules\Application\UseCases')
    ->toHaveSuffix('UseCase');

arch('loaders use the Loader suffix')
    ->expect('DimitrienkoV\LaravelModules\Loaders')
    ->classes()
    ->toHaveSuffix('Loader')
    ->ignoring(ModuleLoaderPipeline::class);

arch('console commands do not depend on concrete persistence or registry internals')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Manifest\ModuleRegistry',
        'DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache',
        'DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository',
        'DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository',
        'DimitrienkoV\LaravelModules\Manifest\FeatureRepository',
        'DimitrienkoV\LaravelModules\Support\LocalFilesystem',
        'DimitrienkoV\LaravelModules\Support\AtomicFileWriter',
        'DimitrienkoV\LaravelModules\Support\AtomicJsonWriter',
    ]);

arch('loaders do not depend on optional UI integrations')
    ->expect('DimitrienkoV\LaravelModules\Loaders')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\MoonShine',
        'MoonShine',
        'Inertia',
    ]);

arch('application layer does not depend on optional UI integrations')
    ->expect('DimitrienkoV\LaravelModules\Application')
    ->not->toUse([
        'MoonShine',
        'Inertia',
    ]);

arch('manifest repository implements its published contract')
    ->expect('DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository')
    ->toImplement('DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface');

arch('state repository implements its published contract')
    ->expect('DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository')
    ->toImplement('DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface');

arch('feature repository implements its published contract')
    ->expect('DimitrienkoV\LaravelModules\Manifest\FeatureRepository')
    ->toImplement('DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface');

test('src does not use global logging or service-location helpers', function (): void {
    $srcDir = realpath(__DIR__ . '/../../src');
    expect($srcDir)->not->toBeFalse();

    // Global helpers that bypass constructor DI / introduce runtime logging.
    // `base_path()` in the service provider (composition root) is sanctioned
    // and intentionally excluded from this list.
    $forbiddenHelpers = [
        'logger', 'logs', 'info', 'app', 'config',
        'resolve', 'value', 'report', 'dispatch', 'event',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = preg_split('/\R/', (string) file_get_contents($file->getPathname())) ?: [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            // Skip comment lines so prose like "malformed config (...)" never trips the scan.
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '*')) {
                continue;
            }
            if (str_starts_with($trimmed, '//')) {
                continue;
            }
            if (str_starts_with($trimmed, '/*')) {
                continue;
            }
            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            foreach ($forbiddenHelpers as $helper) {
                // Bare global call only: not a method (`->info(`), static (`::value(`),
                // variable (`$app(`), namespaced symbol, or a method definition.
                $isGlobalCall = (bool) preg_match('/(?<![\w$>:\\\\])(?<!function\s)' . $helper . '\s*\(/', $line);
                expect($isGlobalCall)->toBeFalse(
                    "Global helper {$helper}() found in {$file->getPathname()} — use constructor DI instead.",
                );
            }
        }
    }
});
