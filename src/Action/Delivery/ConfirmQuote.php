<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConfirmQuote
{

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly OrderFactory $orderFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryManager $deliveryManager,
        private readonly PricingManager $pricingManager,
    )
    {
    }

    public function __invoke(DeliveryQuote $data)
    {
        $delivery = $this->serializer->deserialize($data->getPayload(), Delivery::class, 'jsonld');

        $order = $this->orderFactory->createForDelivery($delivery);
        $this->pricingManager->addDeliveryOrderItem($order, $delivery, new PricingRulesBasedPrice($data->getAmount()));

        $store = $data->getStore();
        $store->addDelivery($delivery);

        $this->deliveryManager->setDefaults($delivery);

        $this->entityManager->persist($order);

        $data->setDelivery($delivery);
        $data->setState(DeliveryQuote::STATE_CONFIRMED);

        return $data;
    }
}
