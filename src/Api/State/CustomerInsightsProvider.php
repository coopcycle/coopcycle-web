<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerInsightsDto;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Sylius\Customer\CustomerInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerInsightsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var CustomerInterface */
        $customer = $this->provider->provide($operation, $uriVariables, $context);

        /** @var OrderRepository */
        $orderRepo = $this->entityManager->getRepository(Order::class);
        $insights = $orderRepo->getCustomerInsights($customer);

        $dto = new CustomerInsightsDto();
        $dto->averageOrderTotal  = $insights['averageOrderTotal'];
        $dto->firstOrderedAt     = $insights['firstOrderedAt'];
        $dto->lastOrderedAt      = $insights['lastOrderedAt'];
        $dto->numberOfOrders     = $insights['numberOfOrders'];
        $dto->favoriteRestaurant = $insights['favoriteRestaurant'];

        return $dto;
    }
}
