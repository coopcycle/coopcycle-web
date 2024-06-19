<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\LoggingUtils;
use AppBundle\Service\OrderManager;
use Psr\Log\LoggerInterface;

class AutoAcceptOrder
{
    public function __construct(
        private OrderManager $orderManager,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
    }

    public function __invoke(Event\OrderCreated $event)
    {
        $order = $event->getOrder();
        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if ($restaurant->isAutoAcceptOrdersEnabled()) {
            $this->orderManager->accept($order);
            $this->checkoutLogger->info('AutoAcceptOrder | accepted', ['order' => $this->loggingUtils->getOrderId($order)]);
        }
    }
}
