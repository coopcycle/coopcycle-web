<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Promotion\Modifier\OrderPromotionsUsageModifier;
use SimpleBus\Message\Bus\MessageBus;

/**
 * @see https://github.com/Sylius/Sylius/blob/60134b783c51c4b9da0b58c5a54482824a8baf8a/src/Sylius/Bundle/CoreBundle/Resources/config/app/state_machine/sylius_order.yml
 */
class ModifyPromotionsUsage
{
    private $usageModifier;

    public function __construct()
    {
        $this->usageModifier = new OrderPromotionsUsageModifier();
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if ($event instanceof Event\OrderCreated) {
            $this->usageModifier->increment($order);
        }

        if ($event instanceof Event\OrderCancelled) {
            $this->usageModifier->decrement($order);
        }
    }
}
