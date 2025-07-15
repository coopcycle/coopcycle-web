<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Api\Dto\InvoiceLineItem;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ExportCommand;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InvoiceLineItemsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductVariantFactory $productVariantFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly SettingsManager $settingsManager,
        private readonly TranslatorInterface $translator,
        private readonly string $locale,
        private readonly bool $packageDeliveryUiPriceBreakdownEnabled,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceClass = $operation->getClass();

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
                $operation,
                $context
            );

            if (
                $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operation, $context)
            ) {
                return $this->postProcessResult(
                    $extension->getResult($qb, $resourceClass, $operation, $context),
                    $operation->getName()
                );
            }
        }

        return $this->postProcessResult($qb->getQuery()->getResult(), $operation->getName());
    }

    /**
     * @param PaginatorInterface|array $data
     */
    private function postProcessResult(iterable $data, string $operationName): iterable
    {
        $orders = iterator_to_array($data);

        // Mark orders as exported
        if (
            '_api_/invoice_line_items/export_get_collection' === $operationName
            || '_api_/invoice_line_items/export/odoo_get_collection' === $operationName
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

        // Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $this->preloadEntities($orders);

        $onDemandDeliveryProduct = $this->productRepository->findOnDemandDeliveryProduct();

        $invoiceLineItems = array_map(fn ($o) => $this->convertToInvoiceLineItem($o, $onDemandDeliveryProduct), $orders);

        if ($data instanceof PaginatorInterface) {
            return new TraversablePaginator(
                new \ArrayIterator($invoiceLineItems),
                $data->getCurrentPage(),
                $data->getItemsPerPage(),
                $data->getTotalItems()
            );
        }

        return $invoiceLineItems;
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

    private function convertToInvoiceLineItem(Order $order, ProductInterface $onDemandDeliveryProduct): InvoiceLineItem
    {
        $delivery = $order->getDelivery();
        $store = $delivery?->getStore();

        $request = $this->requestStack->getCurrentRequest();
        $requestId = $request->headers->get('X-Request-ID');

        $invoiceId = sprintf('%s-%s',
            $requestId,
            substr(hash('sha256', $store?->getId() ?? 0), 0, 7)
        );

        $invoiceDate = new \DateTime();

        $product = '';

        $description = '';

        $orderDate = $order->getShippingTimeRange()?->getUpper() ?? $order->getCreatedAt();

        $descriptionParts = [];

        if ($delivery && $store) {
            $product = $onDemandDeliveryProduct->getName();
            $descriptionParts[] = $onDemandDeliveryProduct->getName();

            if ($this->packageDeliveryUiPriceBreakdownEnabled) {
                //TODO
            } else {
                $descriptionParts = array_merge($descriptionParts, $this->legacyDescription($order, $delivery));
            }

            $descriptionParts[] = Carbon::instance($orderDate)->locale($this->locale)->isoFormat('L');

            $description = sprintf('%s (%s)',
                implode(' - ', $descriptionParts),
                $this->translator->trans('adminDashboard.invoicing.line_item.order_number', [
                    '%number%' => $order->getNumber(),
                ], 'messages')
            );

            // Remove special characters (emojis, etc.) from the description
            // See https://symbl.cc/en/unicode-table/ for a list of Unicode ranges
            $description = preg_replace('/[\x{2600}-\x{27FF}\x{10000}-\x{10FFFF}]/u', '', $description);
        }

        $exports = $order->getExports()->map(fn($export) => $export->getExportCommand())->toArray();

        return new InvoiceLineItem(
            sprintf('%s-%d', $invoiceId, $order->getId()),
            $invoiceId,
            $invoiceDate,
            $store?->getId(),
            $store?->getLegalName() ?? $store?->getName(),
            $this->settingsManager->get('accounting_account') ?? '',
            $product,
            $order->getId(),
            $order->getNumber(),
            $orderDate,
            $description,
            $order->getTotal() - $order->getTaxTotal(),
            $order->getTaxTotal(),
            $order->getTotal(),
            $exports
        );
    }

    /**
     * @return string[]
     */
    private function legacyDescription(OrderInterface $order, Delivery $delivery): array
    {
        $deliveryItem = $order->getItems()->first();

        if (null === $deliveryItem) {
            return [];
        }

        $descriptionParts = [];

        if (1 === count($order->getItems())) {
            // either legacy format (order created before price breakdown is introduced)
            // or arbitrary price
            // or price breakdown format

            $productVariant = $deliveryItem->getVariant();

            if ($this->isPriceBreakdownProductVariant($productVariant)) {
                $descriptionParts[] = $this->productVariantFactory->generateLegacyProductVariantName($delivery);
            } else {
                // legacy format
                // or arbitrary price
                $descriptionParts[] = $productVariant->getName();
            }
        } else {
            // price breakdown format
            $descriptionParts[] = $this->productVariantFactory->generateLegacyProductVariantName($delivery);
        }

        foreach ($delivery->getTasks() as $task) {
            $clientName = $task->getAddress()->getName();

            $descriptionParts[] = sprintf('%s: %s',
                $this->translator->trans(sprintf('task.type.%s', $task->getType())),
                $clientName ?: $task->getAddress()->getStreetAddress());
        }

        return $descriptionParts;
    }

    private function isPriceBreakdownProductVariant(ProductVariantInterface $productVariant): bool
    {
        return str_contains($productVariant->getName(), $this->translator->trans('pricing.variant.order_supplement'))
            || str_contains($productVariant->getName(), $this->translator->trans('pricing.variant.pickup_point'))
            || str_contains($productVariant->getName(), $this->translator->trans('pricing.variant.dropoff_point'));
    }
}
