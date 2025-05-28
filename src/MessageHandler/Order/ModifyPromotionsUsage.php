<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Sylius\Promotion\Modifier\OrderPromotionsUsageModifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see https://github.com/Sylius/Sylius/blob/60134b783c51c4b9da0b58c5a54482824a8baf8a/src/Sylius/Bundle/CoreBundle/Resources/config/app/state_machine/sylius_order.yml
 */
#[AsMessageHandler()]
 class ModifyPromotionsUsage
{
    private $usageModifier;

    public function __construct()
    {
        $this->usageModifier = new OrderPromotionsUsageModifier();
    }

    public function __invoke(OrderCreated|OrderCancelled $event)
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
