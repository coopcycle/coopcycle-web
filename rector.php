<?php

declare(strict_types=1);

use Invenso\Rector\Set\ApiPlatformSetList;
use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Assign\NullCoalescingOperatorRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Rector\Symfony\Set\SymfonySetList;


use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(deadCode: true)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::PHP_83,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_54
    ])
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/src',
    ])
    ->withSkip([
        __DIR__ . '/app/DoctrineMigrations',
        __DIR__ . '/src/Command',

        TypedPropertyFromStrictConstructorRector::class,
        // TypedPropertyRector::class,
        ClosureToArrowFunctionRector::class,
        NullCoalescingOperatorRector::class
        ])
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/AppKernelDevDebugContainer.xml')
    // ->withSets([
    //     ApiPlatformSetList::ANNOTATIONS_TO_ATTRIBUTES,
    // ])
;

