<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Menu\MenuItem;
use Cocur\Slugify\Slugify;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;

class ProductFactory implements ProductFactoryInterface
{
    /**
     * @var ProductFactoryInterface
     */
    private $factory;

    private $slugify;

    /**
     * @param ProductFactoryInterface $factory
     */
    public function __construct(ProductFactoryInterface $factory, Slugify $slugify)
    {
        $this->factory = $factory;
        $this->slugify = $slugify;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew(): ProductInterface
    {
        return $this->factory->createNew();
    }

    /**
     * {@inheritdoc}
     */
    public function createWithVariant(): ProductInterface
    {
        return $this->factory->createWithVariant();
    }

    public function createForMenuItem(MenuItem $menuItem): ProductInterface
    {
        $product = $this->createNew();

        $code = sprintf('CPCCL-FDTCH-%d', $menuItem->getId());

        $slug = sprintf('%d-%s',
            $menuItem->getId(),
            $this->slugify->slugify($menuItem->getName())
        );

        $product->setCode($code);
        $product->setName($menuItem->getName());
        $product->setSlug($slug);

        return $product;
    }
}
