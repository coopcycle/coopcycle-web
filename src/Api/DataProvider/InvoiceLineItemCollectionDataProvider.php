<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Entity\Sylius\ExportCommand;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

final class InvoiceLineItemCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && (
                'invoice_line_items' === $operationName
                || 'invoice_line_items_export' === $operationName
                || 'invoice_line_items_odoo_export' === $operationName
            );
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $qb = $this->entityManager->getRepository(Order::class)->createOptimizedQueryBuilder('o')
            // Additional optimization: preload relations with composite keys
            ->addSelect('v', 'ex')
            ->leftJoin('o.vendors', 'v')
            ->leftJoin('o.exports', 'ex');

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection(
                $qb,
                $queryNameGenerator,
                $resourceClass,
                $operationName,
                $context
            );

            if (
                $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operationName, $context) // @phpstan-ignore arguments.count
            ) {
                return $this->postProcessResult(
                    $extension->getResult($qb, $resourceClass, $operationName, $context), // @phpstan-ignore arguments.count
                    $operationName
                );
            }
        }

        return $this->postProcessResult($qb->getQuery()->getResult(), $operationName);
    }

    private function postProcessResult(iterable $data, string $operationName): iterable
    {
        $orders = iterator_to_array($data);

        // Mark orders as exported
        if (
            'invoice_line_items_export' === $operationName
            || 'invoice_line_items_odoo_export' === $operationName
        ) {

            $request = $this->requestStack->getCurrentRequest();
            $requestId = $request->headers->get('X-Request-ID');

            $exportCommand = new ExportCommand(
                $this->security->getUser(),
                $requestId,
            );
            $exportCommand->addOrders($orders);

            $this->entityManager->persist($exportCommand);
            $this->entityManager->flush();
        }

        //Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $this->preloadEntities($orders);

        return $data;
    }

    private function preloadEntities(array $orders): void
    {
        $preloader = new EntityPreloader($this->entityManager);

        $orderItems = $preloader->preload($orders, 'items');
        $preloader->preload($orders, 'adjustments');

        $preloader->preload($orderItems, 'adjustments');
        $preloader->preload($orderItems, 'variant');

        $delivery = $preloader->preload($orders, 'delivery');
        $preloader->preload($delivery, 'store');
        $taskCollectionItems = $preloader->preload($delivery, 'items');
        $preloader->preload($taskCollectionItems, 'task');
    }
}
