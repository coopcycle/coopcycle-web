<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Api\Dto\InvoiceLineItemSepaChargeResult;
use AppBundle\Entity\Sylius\ExportCommand;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Store;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Stripe\Exception\ApiErrorException;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Charges, via Stripe SEPA Direct Debit, the stores whose orders match the
 * same filters used by the /invoice_line_items export endpoints
 * (store[], date[after]/date[before], state[]).
 *
 * One ExportCommand (the existing "this batch of orders was invoiced"
 * record) is created per charged store, exactly like the CSV/Odoo export
 * endpoints already do, so a store's orders are not picked up again by a
 * later "not yet invoiced" export/charge.
 */
final class InvoiceLineItemsSepaChargeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly StripeManager $stripeManager,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    /**
     * @return InvoiceLineItemSepaChargeResult[]
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $orders = $this->getFilteredOrders($operation, $context);

        $ordersByStore = [];
        foreach ($orders as $order) {
            $storeId = $order->getDelivery()?->getStore()?->getId();

            // FIXME: currently only On Demand Delivery orders for stores are supported
            if (null === $storeId) {
                continue;
            }

            $ordersByStore[$storeId][] = $order;
        }

        $currencyCode = $this->currencyContext->getCurrencyCode();

        $results = [];
        foreach ($ordersByStore as $storeOrders) {
            $results[] = $this->chargeStore($storeOrders, $currencyCode);
        }

        return $results;
    }

    private function chargeStore(array $orders, string $currencyCode): InvoiceLineItemSepaChargeResult
    {
        /** @var Store $store */
        $store = $orders[0]->getDelivery()->getStore();

        $amount = array_reduce($orders, fn ($carry, $order) => $carry + $order->getTotal(), 0);

        if (!$store->isSepaMandateActive()) {
            return new InvoiceLineItemSepaChargeResult(
                $store->getId(),
                $store->getName(),
                'skipped_no_mandate',
                $amount
            );
        }

        $exportCommand = new ExportCommand($this->security->getUser(), sprintf('sepa-%s', uniqid()));
        $exportCommand->addOrders($orders);

        $this->entityManager->persist($exportCommand);
        $this->entityManager->flush();

        try {
            $this->stripeManager->setupStripeApi();

            $paymentIntent = $this->stripeManager->chargeStoreViaSepa($store, $amount, $currencyCode, $exportCommand);

            $this->entityManager->flush();

            return new InvoiceLineItemSepaChargeResult(
                $store->getId(),
                $store->getName(),
                'charged',
                $amount,
                $paymentIntent->id
            );
        } catch (ApiErrorException $e) {
            $exportCommand->setPaymentStatus('failed');
            $this->entityManager->flush();

            return new InvoiceLineItemSepaChargeResult(
                $store->getId(),
                $store->getName(),
                'failed',
                $amount,
                null,
                $e->getMessage()
            );
        }
    }

    private function getFilteredOrders(Operation $operation, array $context): array
    {
        $qb = $this->entityManager->getRepository(Order::class)->createOptimizedQueryBuilder('o')
            ->addSelect('v')
            ->leftJoin('o.vendors', 'v');

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection(
                $qb,
                $queryNameGenerator,
                Order::class,
                $operation,
                $context
            );
        }

        $orders = $qb->getQuery()->getResult();

        $preloader = new EntityPreloader($this->entityManager);
        $delivery = $preloader->preload($orders, 'delivery');
        $preloader->preload($delivery, 'store');

        return $orders;
    }
}
