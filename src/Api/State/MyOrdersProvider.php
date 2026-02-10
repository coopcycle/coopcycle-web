<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Entity\User;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
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

        $qb = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->innerJoin(User::class, 'u', Join::WITH, 'u.customer = c.id')
            ->where('u.id = :user')
            ->setParameter('user', $this->security->getUser());

        $customer = $qb->getQuery()->getOneOrNullResult();

        $queryBuilder = $this->entityManager->getRepository($entityClass)
            ->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state != :state_cart')
            ->orderBy('o.createdAt', 'DESC')
            ->setParameter('customer', $customer)
            ->setParameter('state_cart', Order::STATE_CART)
            ;

        // https://github.com/coopcycle/coopcycle-web/issues/5188
        // FIXME Doesn't work
        // $queryBuilder->getQuery()->setHint(Paginator::HINT_ENABLE_DISTINCT, false);

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {

            $extension->applyToCollection($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($entityClass, $operation, $context)) {
                return $this->preload($extension->getResult($queryBuilder, $entityClass, $operation, $context));
            }
        }

        // FIXME
        // Serialize only what is needed for the app.

        $orders = $queryBuilder->getQuery()->getResult();

        return $this->preload($orders);
    }

    private function preload(iterable $result): iterable
    {
        $orders = \iterator_to_array($result);

        $preloader = new EntityPreloader($this->entityManager);
        $preloader->preload($orders, 'adjustments');
        $preloader->preload($orders, 'payments');

        $items = $preloader->preload($orders, 'items');
        $variant = $preloader->preload($items, 'variant');
        $product = $preloader->preload($variant, 'product');

        if ($result instanceof PaginatorInterface) {
            return new TraversablePaginator(
                new \ArrayIterator($orders),
                $result->getCurrentPage(),
                $result->getItemsPerPage(),
                $result->getTotalItems()
            );
        }

        return $orders;
    }
}
