<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Assign\NullCoalescingOperatorRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {

    // get parameters
    $parameters = $containerConfigurator->parameters();

    // Define what rule sets will be applied
    $parameters->set(Option::SETS, [
        SetList::DEAD_CODE,
        SetList::PHP_74,
        SetList::SYMFONY_44,
    ]);

    // set paths to directories with your code
    $parameters->set(Option::PATHS, [
        __DIR__ . '/app',
        __DIR__ . '/src',
        // __DIR__ . '/tests',
    ]);

    // is your PHP version different from the one your refactor to? [default: your PHP version], uses PHP_VERSION_ID format
    // $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);

    $parameters->set(Option::SKIP, [
        __DIR__ . '/app/DoctrineMigrations',
        __DIR__ . '/src/Command',

        TypedPropertyFromStrictConstructorRector::class,
        TypedPropertyRector::class,
        ClosureToArrowFunctionRector::class,
        NullCoalescingOperatorRector::class,
    ]);

    $parameters->set(
        Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER,
        __DIR__ . '/var/cache/dev/AppKernelDevDebugContainer.xml'
    );

    // get services (needed for register a single rule)
    // $services = $containerConfigurator->services();

    // register a single rule
    // $services->set(TypedPropertyRector::class);
};
