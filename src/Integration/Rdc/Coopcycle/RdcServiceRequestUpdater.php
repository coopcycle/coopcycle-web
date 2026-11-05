<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\Enum\ServiceStatus;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Applies the updates a requestor makes to its service request. Only the
 * cancellation is supported for now: the other changes are ignored.
 */
class RdcServiceRequestUpdater
{
    /**
     * Set on a delivery cancelled by its requestor: there is no need to cancel
     * its service request back on the requestor's node.
     */
    public const CANCELLED_BY_REQUESTOR = 'rdc.cancelled_by_requestor';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderManager $orderManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function update(RdcApiServiceRequest $apiRequest, string $loUri): void
    {
        if (ServiceStatus::CANCELLED !== $apiRequest->requestStatus) {
            $this->logger->info('RDC service request update ignored: only cancellations are supported', [
                'lo_uri' => $loUri,
                'request_status' => $apiRequest->requestStatus?->value,
            ]);

            return;
        }

        /** @var DeliveryRepository $repository */
        $repository = $this->entityManager->getRepository(Delivery::class);
        $delivery = $repository->findByLoUri($loUri);
        if (is_null($delivery)) {
            $this->logger->warning('RDC service request cancelled, but no delivery was created for it', [
                'lo_uri' => $loUri,
            ]);

            return;
        }

        $order = $delivery->getOrder();
        if (is_null($order) || OrderInterface::STATE_CANCELLED === $order->getState()) {
            $this->logger->info('RDC service request cancelled, its delivery already is', [
                'lo_uri' => $loUri,
                'delivery_id' => $delivery->getId(),
            ]);

            return;
        }

        try {
            $this->orderManager->cancel($order, 'Cancelled by the requestor');
        } catch (HandlerFailedException $e) {
            // Already delivered, for instance: nothing to undo, a dispatcher has to look at it
            if ([] === $e->getWrappedExceptions(OrderNotCancellableException::class)) {
                throw $e;
            }

            $this->logger->error('RDC service request cancelled, but its delivery can no longer be', [
                'lo_uri' => $loUri,
                'delivery_id' => $delivery->getId(),
                'order_state' => $order->getState(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Saved with the cancelled tasks, before their status updates go out
        $delivery->setMetadata(self::CANCELLED_BY_REQUESTOR, true);
        $this->entityManager->flush();

        $this->logger->info('RDC delivery cancelled by its requestor', [
            'lo_uri' => $loUri,
            'delivery_id' => $delivery->getId(),
        ]);
    }
}
