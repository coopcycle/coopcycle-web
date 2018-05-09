<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;

class OrderFactory implements FactoryInterface
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @param FactoryInterface $factory
     */
    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
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

        return $order;
    }
}
