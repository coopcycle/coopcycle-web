<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Entity\ReusablePackaging\Unit as ReusablePackagingUnit;
use AppBundle\ReusablePackaging\InventoryOperator;
use SimpleBus\Message\Bus\MessageBus;

class UpdateReusablePackaging
{
    private $inventoryOperator;

    public function __construct(InventoryOperator $inventoryOperator)
    {
        $this->inventoryOperator = $inventoryOperator;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if ($event instanceof Event\OrderPicked) {
            foreach ($order->getRestaurant()->getReusablePackagings() as $reusablePackaging) {

                if (!$reusablePackaging->isTracked()) {
                    continue;
                }

                $units = $order->countReusablePackagingUnits();

                if ($units > 0) {
                    $reusablePackaging->setOnHand($reusablePackaging->getOnHand() - $units);
                }
            }
        }

        if ($event instanceof Event\OrderDropped) {
            if ($order->countReusablePackagingUnits() > 0) {

                $customer = $order->getCustomer();

                foreach ($order->getRestaurant()->getReusablePackagings() as $reusablePackaging) {

                    if (!$reusablePackaging->isTracked()) {
                        continue;
                    }

                    $customer->addReusablePackagingOperation($reusablePackaging, 'increase', $order->countReusablePackagingUnits());

                    if ($order->getGiveBackUnits() > 0) {
                        $customer->addReusablePackagingOperation($reusablePackaging, 'decrease', $order->getGiveBackUnits());
                    }
                }
            }
        }
    }
}
