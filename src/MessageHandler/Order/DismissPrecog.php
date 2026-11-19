<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Service\Precog\PrecogTracker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Takes the ghost off the dispatch board once the order it was forecasting
 * actually exists.
 *
 * The real dropoff arrives on the same channel as `task:created`, so the halo
 * visibly becomes a task instead of sitting behind one. In Sylius the cart and
 * the order are the same row, so the id the ghost was keyed on is the id here --
 * no bookkeeping needed to connect the two.
 */
#[AsMessageHandler]
class DismissPrecog
{
    public function __construct(private PrecogTracker $precogTracker)
    {
    }

    public function __invoke(OrderCreated $event): void
    {
        $order = $event->getOrder();

        if (null === $order->getId()) {
            return;
        }

        $this->precogTracker->forget($order->getId());
    }
}
