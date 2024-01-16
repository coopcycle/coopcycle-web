<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Service\LoggingUtils;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class OrderLogFinishProcessor implements OrderProcessorInterface
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
        $this->checkoutLogger->info(sprintf('Order %s | OrderLogFinishProcessor | processing finished | triggered by: %s; at: %s',
            $this->loggingUtils->getOrderId($order),
            $this->loggingUtils->getRequest(),
            $this->loggingUtils->getBacktrace()));
    }
}
