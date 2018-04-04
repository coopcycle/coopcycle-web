<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Menu\MenuItem;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;

class MenuItemListener
{
    private $productFactory;
    private $productVariantFactory;
    private $productRepository;
    private $productVariantRepository;

    public function __construct(
        ProductFactoryInterface $productFactory,
        ProductVariantFactoryInterface $productVariantFactory,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository)
    {
        $this->productFactory = $productFactory;
        $this->productVariantFactory = $productVariantFactory;
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
    }

    public function postPersist(MenuItem $menuItem, LifecycleEventArgs $args)
    {
        $product = $this->productFactory->createForMenuItem($menuItem);
        $productVariant = $this->productVariantFactory->createForMenuItem($menuItem);

        $productVariant->setProduct($product);

        $this->productRepository->add($product);
        $this->productVariantRepository->add($productVariant);
    }
}
