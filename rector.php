<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictStringParamConcatRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Architecture',
    ])
    ->withSkip([
        __DIR__ . '/tests/*/Fixtures',
        // Generator traits deliberately mirror the untyped parameter signatures of
        // Laravel's GeneratorCommand (e.g. getDefaultNamespace($rootNamespace)).
        // Adding a native string type there breaks LSP against the vendor parent.
        StrictStringParamConcatRector::class => [
            __DIR__ . '/src/Console/Concerns',
        ],
        // RouteLoader probes the optional Inertia package via string class names
        // (class_exists('Inertia\Inertia')). Rewriting them to ::class would force
        // a `use Inertia\...` import and break the "loaders do not depend on
        // optional UI integrations" architecture test.
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Loaders/RouteLoader.php',
        ],
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        SetList::TYPE_DECLARATION,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::STRICT_BOOLEANS,
        SetList::INSTANCEOF,
    ])
    ->withImportNames(
        removeUnusedImports: true,
    );
