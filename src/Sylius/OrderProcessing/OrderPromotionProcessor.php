<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Promotion\Processor\PromotionProcessorInterface;
use Webmozart\Assert\Assert;

/**
 * @see https://github.com/Sylius/Sylius/blob/500f0a9870667c5751d59ff0d9bf4e4227548aed/src/Sylius/Component/Core/OrderProcessing/OrderPromotionProcessor.php
 */
final class OrderPromotionProcessor implements OrderProcessorInterface
{
    private $promotionProcessor;

    public function __construct(
        PromotionProcessorInterface $promotionProcessor)
    {
        $this->promotionProcessor = $promotionProcessor;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->promotionProcessor->process($order);
    }
}
