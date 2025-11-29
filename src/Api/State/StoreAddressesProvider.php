<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Store;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\RequestStack;

final class StoreAddressesProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $provider,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('type') && 'dropoff' === $request->query->get('type')) {

            @trigger_error('Using the "type" parameter is deprecated', \E_USER_DEPRECATED);

            return $this->getDropoffAddresses($uriVariables['id']);
        }

        return $this->provider->provide($operation, $uriVariables, $context);
    }

    private function getDropoffAddresses($storeId)
    {
        $qb = $this->entityManager->getRepository(Address::class)->createQueryBuilder('a');

        $qb->join(Task::class, 't', Join::WITH, 'a.id = t.address');
        $qb->join(Delivery::class, 'd', Join::WITH, 'd.id = t.delivery AND d.store = :store');

        $qb
            ->andWhere('t.type = :type')
            ->setParameter('store', $storeId)
            ->setParameter('type', 'DROPOFF');

        $qb->groupBy('a.id');

        return $qb->getQuery()->getResult();
    }
}
