<?php

namespace AppBundle\Api\DataProvider;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Store;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\RequestStack;

final class StoreAddressesSubresourceDataProvider implements SubresourceDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(
        RequestStack $requestStack,
        ManagerRegistry $doctrine)
    {
        $this->requestStack = $requestStack;
        $this->doctrine = $doctrine;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Address::class === $resourceClass && $operationName === 'api_stores_addresses_get_subresource';
    }

    /**
     * @throws ItemNotFoundException
     */
    public function getSubresource(string $resourceClass, array $identifiers, array $context, string $operationName = null)
    {
        [ $identifierResourceClass, $identifier ] = current($context['identifiers']);

        $id = (int) $context['subresource_identifiers'][$identifier];

        $store = $this->doctrine->getRepository(Store::class)->find($id);
        if (!$store) {
            throw new ItemNotFoundException();
        }

        $request = $this->requestStack->getCurrentRequest();

        $addresses = [];
        if ($request->query->has('type') && 'dropoff' === $request->query->get('type')) {

            $qb = $this->doctrine->getRepository(Address::class)->createQueryBuilder('a');

            $qb->join(Task::class, 't', Join::WITH, 'a.id = t.address');
            $qb->join(Delivery::class, 'd', Join::WITH, 'd.id = t.delivery AND d.store = :store');

            $qb
                ->andWhere('t.type = :type')
                ->setParameter('store', $store)
                ->setParameter('type', 'DROPOFF');

            $qb->groupBy('a.id');

            return $qb->getQuery()->getResult();
        }

        return $addresses;
    }
}
