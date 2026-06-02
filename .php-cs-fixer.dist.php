<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Symfony\Component\Finder\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

if (is_dir(__DIR__ . '/stubs')) {
    $finder->in(__DIR__ . '/stubs');
}

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        // PER Coding Style 3.0 is the modern successor to the frozen PSR-12.
        // The ":risky" companion set is standalone (only the risky-specific rules),
        // so both entries are required to get the full ruleset.
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        // Rules below are NOT part of @PER-CS3x0 — kept on purpose.
        // (array_syntax, ordered_imports, ordered_class_elements, binary/unary
        //  operator spaces, concat_space, method_argument_space,
        //  single_trait_insert_per_statement and trailing_comma_in_multiline are
        //  already provided by @PER-CS3x0 and were dropped from here.)
        'declare_strict_types' => true,
        'native_function_invocation' => true,
        'single_quote' => true,
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'fully_qualified_strict_types' => true,
        'not_operator_with_successor_space' => true,
        'phpdoc_scalar' => true,
        'phpdoc_align' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'void_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
