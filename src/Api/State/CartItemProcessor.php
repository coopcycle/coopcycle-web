<?php

namespace AppBundle\Api\State;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Utils\OptionsPayloadConverter;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class CartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OptionsPayloadConverter $optionsPayloadConverter,
        private readonly LazyProductVariantResolverInterface $variantResolver,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param CartItemInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // $cart = $context['previous_data'];

        var_dump($this->entityManager->contains($data));

        $cart = $this->entityManager->getRepository(Order::class)->find($uriVariables['id']);

        $product = $this->productRepository->findOneByCode($data->product);

        if (!$product->hasOptions()) {
            $productVariant = $this->variantResolver->getVariant($product);
        } else {
            if (!$product->hasNonAdditionalOptions() && empty($data->options)) {
                $productVariant = $this->variantResolver->getVariant($product);
            } else {
                $optionValues = $this->optionsPayloadConverter->convert($product, $data->options);
                $productVariant = $this->variantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($productVariant);
        $orderItem->setUnitPrice($productVariant->getPrice());

        $this->orderItemQuantityModifier->modify($orderItem, $data->quantity);

        $this->orderModifier->addToOrder($cart, $orderItem);

        // $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }
}
