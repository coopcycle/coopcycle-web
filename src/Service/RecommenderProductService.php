<?php

namespace AppBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecommenderProductService
{
    private const N = 5;

    public function __construct(
        private readonly HttpClientInterface $recommenderClient,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return Product[] */
    public function getProductsForOrder(Order $order): array
    {
        $customer   = $order->getCustomer();
        $restaurant = $order->getRestaurant() ?? $order->getVendor();

        if (null === $customer || null === $restaurant) {
            return [];
        }

        $customerIri   = $this->iriConverter->getIriFromResource($customer);
        $restaurantIri = $this->iriConverter->getIriFromResource($restaurant);

        $cartProductIds = [];
        foreach ($order->getItems() as $item) {
            $productId = $item->getVariant()?->getProduct()?->getId();
            if (null !== $productId) {
                $cartProductIds[] = $productId;
            }
        }

        try {
            $iris = $this->recommenderClient
                ->request('GET', '/recommendations', [
                    'query' => ['customer' => $customerIri, 'type' => 'product', 'n' => 20, 'restaurant' => $restaurantIri],
                ])
                ->toArray()['recommendations'] ?? [];

            if ($iris === []) {
                return [];
            }

            $ids = array_values(array_diff(
                array_filter(array_map(fn(string $iri) => (int) basename($iri), $iris)),
                $cartProductIds
            ));

            return array_slice(
                $this->entityManager->getRepository(Product::class)->findBy([
                    'id'         => $ids,
                    'enabled'    => true,
                    'restaurant' => $restaurant,
                ]),
                0,
                self::N
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
