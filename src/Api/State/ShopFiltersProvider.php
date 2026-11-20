<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShopFilters;
use AppBundle\Service\ShopFilterRegistry;

final class ShopFiltersProvider implements ProviderInterface
{
    public function __construct(private ShopFilterRegistry $shopFilterRegistry)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShopFilters
    {
        return new ShopFilters($this->shopFilterRegistry->getCategories());
    }
}
