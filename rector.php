<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use RectorLaravel\Rector\MethodCall\ContainerBindConcreteWithClosureOnlyRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    // PHP version is read from composer.json ("php": "^8.3").
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    // Laravel upgrade sets up to the installed framework version.
    ->withComposerBased(laravel: true)
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // Laravel's own code is not strict-typed; adding strict_types to every
        // file is a project-wide decision, not something a tool should impose.
        SafeDeclareStrictTypesRector::class,
        // Pure style rewrites: keep closures vs. arrow functions and explicit
        // container bindings as the author wrote them.
        ClosureToArrowFunctionRector::class,
        ContainerBindConcreteWithClosureOnlyRector::class,
    ]);
