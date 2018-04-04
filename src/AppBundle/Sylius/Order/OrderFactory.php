<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;

class OrderFactory implements FactoryInterface
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    private $adjustmentFactory;

    /**
     * @param FactoryInterface $factory
     */
    public function __construct(
        FactoryInterface $factory,
        AdjustmentFactoryInterface $adjustmentFactory)
    {
        $this->factory = $factory;
        $this->adjustmentFactory = $adjustmentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        return $this->factory->createNew();
    }

    public function createForRestaurant(Restaurant $restaurant): OrderInterface
    {
        $order = $this->createNew();
        $order->setRestaurant($restaurant);

        $adjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::DELIVERY_ADJUSTMENT,
            'Livraison',
            (int) ($restaurant->getFlatDeliveryPrice() * 100),
            $neutral = false
        );
        $order->addAdjustment($adjustment);

        return $order;
    }
}
