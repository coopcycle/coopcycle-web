<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class OrderDisabledProductProcessor implements OrderProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()->getProduct();
            if (!$product->isEnabled()) {
                $order->removeItem($item);
            }
        }
    }
}
