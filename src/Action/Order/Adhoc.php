<?php

namespace AppBundle\Action\Order;

use AppBundle\Sylius\Order\OrderFactory;
use Nucleos\UserBundle\Util\CanonicalizerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;

class Adhoc
{
    public function __construct(
        OrderFactory $orderFactory,
        OrderModifierInterface $orderModifier,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        ProductFactoryInterface $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductVariantFactoryInterface $variantFactory,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        RepositoryInterface $customerRepository,
        CanonicalizerInterface $canonicalizer,
        FactoryInterface $customerFactory)
    {
        $this->orderFactory = $orderFactory;
        $this->orderModifier = $orderModifier;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->variantFactory = $variantFactory;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->customerRepository = $customerRepository;
        $this->canonicalizer = $canonicalizer;
        $this->customerFactory = $customerFactory;
    }

    public function __invoke($data)
    {
        $order = null;

        if (!$data->restaurant || !$data->customer || !$data->items) {
            return $data;
        }

        $order = $this->orderFactory->createForRestaurant($data->restaurant);

        foreach($data->items as $item) {
            if (!isset($item['name']) || !isset($item['price']) || !isset($item['taxCategory'])) {
                return null;
            }

            $uuid = Uuid::uuid4()->toString();

            $product = $this->productFactory->createNew();
            $product->setName($item['name']);
            $product->setCode($uuid);
            $product->setSlug($uuid);
            $product->setEnabled(true);

            $this->productRepository->add($product);

            $variant = $this->variantFactory->createForProduct($product);
            $variant->setName($product->getName());
            $variant->setCode(Uuid::uuid4()->toString());
            $variant->setPrice((int) $item['price']);

            $taxCategory = $this->taxCategoryRepository->findOneBy(['code' => $item['taxCategory']]);
            $variant->setTaxCategory($taxCategory);

            $product->addVariant($variant);
            $product->setRestaurant($data->restaurant);

            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($variant);
            $orderItem->setUnitPrice($variant->getPrice());

            $this->orderItemQuantityModifier->modify($orderItem, 1);
            $this->orderModifier->addToOrder($order, $orderItem);
        }

        if (isset($data->customer['email'])) {
            $customer = $this->findOrCreateCustomer($data->customer);
            $order->setCustomer($customer);
        }

        return $order;
    }

    private function findOrCreateCustomer($customerData)
    {
        $customer = $this->customerRepository
            ->findOneBy([
                'emailCanonical' => $this->canonicalizer->canonicalize($customerData['email'])
            ]);

        if (!$customer) {
            $customer = $this->customerFactory->createNew();

            $customer->setEmail($customerData['email']);
            $customer->setEmailCanonical($this->canonicalizer->canonicalize($customerData['email']));
        }

        $customer->setTelephone($customerData['phoneNumber']);
        $customer->setFullName($customerData['fullName']);

        return $customer;
    }
}
