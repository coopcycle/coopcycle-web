<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\CompositeOrderProcessor;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderOptionsFeeProcessor implements OrderProcessorInterface
{
    private $compositeProcessor;

    public function __construct(
        OrderOptionsProcessor $optionsProcessor,
        OrderFeeProcessor $feeProcessor,
        OrderVendorProcessor $vendorProcessor)
    {
        $this->compositeProcessor = new CompositeOrderProcessor();

        $this->compositeProcessor->addProcessor($optionsProcessor, 64);
        $this->compositeProcessor->addProcessor($feeProcessor, 32);
        $this->compositeProcessor->addProcessor($vendorProcessor, 16);
    }

    /**
     * {@inheritdoc}
     */
    public function process(OrderInterface $order): void
    {
        $this->compositeProcessor->process($order);
    }
}
