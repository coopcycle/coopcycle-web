<?php

declare(strict_types=1);

use Invenso\Rector\Set\ApiPlatformSetList;
use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\CodeQuality\Rector\ClassMethod\ActionSuffixRemoverRector;
use Rector\Set\ValueObject\SetList;
use Rector\DeadCode\Rector\If_\ReduceAlwaysFalseIfOrRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(deadCode: true)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::PHP_83,
        // SymfonySetList::SYMFONY_CODE_QUALITY,
        // SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_54
    ])
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/src',
    ])
    ->withSkip([
        __DIR__ . '/app/DoctrineMigrations',
        ActionSuffixRemoverRector::class,
        ReduceAlwaysFalseIfOrRector::class
    ])
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/AppKernelDevDebugContainer.xml')
    ->withAttributesSets()
    ->withSets([
        ApiPlatformSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES
    ])
;

