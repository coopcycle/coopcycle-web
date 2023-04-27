<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Entity\Delivery;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class Create
{
    use DeliveryTrait;

    public function __construct(
        private DeliveryManager $deliveryManager,
        private EntityManagerInterface $entityManager,
        private OrderManager $orderManager,
        private OrderFactory $orderFactory
    )
    { }

    /**
     * @throws NoRuleMatchedException
     */
    public function __invoke(Delivery $data, Request $request)
    {
        $store = $data->getStore();
        if ($store->getCreateOrders()) {
            $price = $this->getDeliveryPrice($data, $store->getPricingRuleSet(), $this->deliveryManager);
            $order = $this->createOrderForDelivery($this->orderFactory, $data, $price);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $this->orderManager->onDemand($order);
        }

        return $data;
    }

    protected function getDeliveryRoutes()
    {
        // TODO: Implement getDeliveryRoutes() method.
    }
}
