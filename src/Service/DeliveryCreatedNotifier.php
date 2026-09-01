<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveriesCreated;
use AppBundle\Message\DeliveryCreated;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches the notifications sent when a delivery is created.
 *
 * While a batch is started, notifications are held back,
 * and replaced by a single recap notification when the batch ends.
 * This avoids flooding admins when many deliveries are created in a row,
 * i.e when orders are generated from recurrence rules.
 */
class DeliveryCreatedNotifier
{
    private bool $batching = false;

    /**
     * @var Delivery[]
     */
    private array $batch = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function notify(Delivery $delivery): void
    {
        if ($this->batching) {
            $this->batch[] = $delivery;

            return;
        }

        $this->messageBus->dispatch(new DeliveryCreated($delivery));
    }

    public function startBatch(): void
    {
        $this->batching = true;
        $this->batch = [];
    }

    /**
     * Ends the current batch, and dispatches a single notification
     * for the deliveries created in the meantime.
     */
    public function endBatch(): void
    {
        if (!$this->batching) {
            return;
        }

        $deliveries = $this->batch;

        $this->batching = false;
        $this->batch = [];

        if (0 === count($deliveries)) {
            return;
        }

        // No need for a recap when there is only one delivery
        if (1 === count($deliveries)) {
            $this->messageBus->dispatch(new DeliveryCreated(current($deliveries)));

            return;
        }

        $this->messageBus->dispatch(new DeliveriesCreated($deliveries));
    }
}
