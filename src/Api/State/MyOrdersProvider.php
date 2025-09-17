<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Entity\User;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Security\Core\Security;

final class MyOrdersProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $entityClass = $operation->getClass();

        $queryBuilder = $this->entityManager->getRepository($entityClass)
            ->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->innerJoin(Customer::class, 'c', Join::WITH, 'o.customer = c.id')
            ->innerJoin(User::class, 'u', Join::WITH, 'u.customer = c.id')
            ->where('u.id = :user')
            ->andWhere('o.state != :state_cart')
            ->setParameter('user', $this->security->getUser())
            ->setParameter('state_cart', Order::STATE_CART)
            ;

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($entityClass, $operation, $context)) {
                return $extension->getResult($queryBuilder, $entityClass, $operation, $context);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
