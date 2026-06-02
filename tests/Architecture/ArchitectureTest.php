<?php

declare(strict_types=1);

use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Support\Logging\LogEvent;
use DimitrienkoV\LaravelModules\Support\Logging\LifecyclePhase;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesListCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesInstallCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesUpdateCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesEnableCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Contracts\ModuleExceptionInterface;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
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

            if (str_contains((string) $path, '/Fixtures/')) {
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

arch('loader value objects are final readonly')
    ->expect([
        LoadReport::class,
        PipelineRunSummary::class,
    ])
    ->toBeReadonly()
    ->toBeFinal();

arch('loader value object enums are string backed')
    ->expect([
        LoadStatus::class,
        SkipReason::class,
    ])
    ->toBeStringBackedEnums();

arch('diagnostics implementations are final and implement the contract')
    ->expect('DimitrienkoV\LaravelModules\Support\Logging')
    ->classes()
    ->toBeFinal()
    ->toImplement(ModuleDiagnosticsInterface::class)
    ->ignoring([
        LogEvent::class,
        LifecyclePhase::class,
    ]);

arch('logging event enums are string backed')
    ->expect([
        LogEvent::class,
        LifecyclePhase::class,
    ])
    ->toBeStringBackedEnums();

arch('loaders are final and implement LoaderInterface')
    ->expect('DimitrienkoV\LaravelModules\Loaders')
    ->classes()
    ->toBeFinal()
    ->toImplement(LoaderInterface::class)
    ->ignoring([ModuleLoaderPipeline::class, 'DimitrienkoV\LaravelModules\Loaders\VO']);

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
    ->not->toUse(ModuleKind::class);

arch('application layer does not depend on loaders or moonshine')
    ->expect('DimitrienkoV\LaravelModules\Application')
    ->not->toUse([
        'DimitrienkoV\LaravelModules\Loaders',
        'DimitrienkoV\LaravelModules\MoonShine',
    ]);

arch('ZipExtractor is final readonly')
    ->expect(ZipExtractor::class)
    ->toBeFinal()
    ->toBeReadonly();

arch('application enums are string backed enums')
    ->expect('DimitrienkoV\LaravelModules\Application\Enums')
    ->toBeStringBackedEnums();

arch('optimize commands do not depend on concrete registry or cache classes')
    ->expect(ModulesOptimizeCommand::class)
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
    ]);

arch('optimize clear command does not depend on concrete registry or cache classes')
    ->expect(ModulesOptimizeClearCommand::class)
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
    ]);

arch('list command does not depend on concrete registry')
    ->expect(ModulesListCommand::class)
    ->not->toUse([
        ModuleRegistryInterface::class,
        ModuleRegistry::class,
    ]);

arch('lifecycle commands do not depend on concrete infrastructure classes')
    ->expect([
        MakeModuleCommand::class,
        ModulesInstallCommand::class,
        ModulesUpdateCommand::class,
        ModulesRemoveCommand::class,
        ModulesEnableCommand::class,
        ModulesDisableCommand::class,
    ])
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
        ModuleManifestRepository::class,
        ModuleStateRepository::class,
        LocalFilesystem::class,
        AtomicFileWriter::class,
        AtomicJsonWriter::class,
    ]);

arch('exceptions implement ModuleExceptionInterface')
    ->expect('DimitrienkoV\LaravelModules\Exceptions')
    ->classes()
    ->toImplement(ModuleExceptionInterface::class);

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
    ->ignoring([ModuleLoaderPipeline::class, 'DimitrienkoV\LaravelModules\Loaders\VO']);

arch('console commands do not depend on concrete persistence or registry internals')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands')
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
        ModuleManifestRepository::class,
        ModuleStateRepository::class,
        FeatureRepository::class,
        LocalFilesystem::class,
        AtomicFileWriter::class,
        AtomicJsonWriter::class,
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
    ->expect(ModuleManifestRepository::class)
    ->toImplement(ModuleManifestRepositoryInterface::class);

arch('state repository implements its published contract')
    ->expect(ModuleStateRepository::class)
    ->toImplement(ModuleStateRepositoryInterface::class);

arch('feature repository implements its published contract')
    ->expect(FeatureRepository::class)
    ->toImplement(FeatureRepositoryInterface::class);

arch('module-aware generators are final')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands\Make')
    ->classes()
    ->toBeFinal();

arch('module-aware generators do not depend on concrete registry or persistence')
    ->expect('DimitrienkoV\LaravelModules\Console\Commands\Make')
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
        ModuleManifestRepository::class,
        ModuleStateRepository::class,
    ]);

arch('generator concerns resolve modules through the registry contract only')
    ->expect('DimitrienkoV\LaravelModules\Console\Concerns')
    ->not->toUse([
        ModuleRegistry::class,
        ModuleRegistryCache::class,
        ModuleManifestRepository::class,
        ModuleStateRepository::class,
    ]);

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
