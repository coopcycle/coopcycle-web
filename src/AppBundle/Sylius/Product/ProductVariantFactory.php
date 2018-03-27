<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ProductVariantFactory implements ProductVariantFactoryInterface
{
    /**
     * @var ProductFactoryInterface
     */
    private $factory;

    private $productRepository;

    private $translator;

    /**
     * @param ProductFactoryInterface $factory
     */
    public function __construct(
        ProductVariantFactoryInterface $factory,
        ProductRepositoryInterface $productRepository,
        TranslatorInterface $translator)
    {
        $this->factory = $factory;
        $this->productRepository = $productRepository;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew(): ProductVariantInterface
    {
        return $this->factory->createNew();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProduct(ProductInterface $product): ProductVariantInterface
    {
        return $this->factory->createForProduct($product);
    }

    public function createForDelivery(Delivery $delivery): ProductVariantInterface
    {
        // TODO Check Delivery has price, etc...

        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        $productVariant = $this->createForProduct($product);

        $name = sprintf('Livraison %s, %d km',
            $this->translator->trans(sprintf('vehicle.%s', $delivery->getVehicle())),
            number_format($delivery->getDistance() / 1000, 2)
        );

        $productVariant->setName($name);
        $productVariant->setPosition(1);

        $productVariant->setPrice((int) $delivery->getTotalIncludingTax() * 100);
        $productVariant->setTaxCategory($delivery->getTaxCategory());

        // TODO Make sure the same variant does not exist

        $hash = sprintf('%s-%d-%d', $delivery->getVehicle(), $delivery->getDistance(), $productVariant->getPrice());
        $code = sprintf('CPCCL-ODDLVR-%s', strtoupper(substr(sha1($hash), 0, 7)));

        $productVariant->setCode($code);

        return $productVariant;
    }
}
