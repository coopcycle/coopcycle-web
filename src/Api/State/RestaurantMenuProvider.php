<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class RestaurantMenuProvider implements ProviderInterface
{
    public function __construct(
        private ItemProvider $provider,
        private EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $menu = $this->provider->provide($operation, $uriVariables, $context);

        if (null !== $menu) {
            $preloader = new EntityPreloader($this->entityManager);

            $preloader->preload([$menu], 'children');
            $taxonProducts = $preloader->preload($menu->getChildren()->toArray(), 'taxonProducts');
            $products = $preloader->preload($taxonProducts, 'product');

            $variants = $preloader->preload($products, 'variants');
            $options = $preloader->preload($products, 'options');

            $preloader->preload($products, 'images');
        }

        return $menu;
    }
}
