<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Service\LoggingUtils;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class OrderDisabledProductProcessor implements OrderProcessorInterface
{

    public function __construct(
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()->getProduct();
            if (!$product->isEnabled()) {
                $this->checkoutLogger->info(sprintf('Order %s | OrderDisabledProductProcessor | removing disabled product %s',
                    $this->loggingUtils->getOrderId($order), $product->getCode()));

                $order->removeItem($item);
            }
        }
    }
}
