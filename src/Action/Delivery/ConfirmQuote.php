<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConfirmQuote
{
    private $serializer;
    private $orderFactory;
    private $entityManager;

    public function __construct(
        SerializerInterface $serializer,
        OrderFactory $orderFactory,
        EntityManagerInterface $entityManager,
        DeliveryManager $deliveryManager)
    {
        $this->serializer = $serializer;
        $this->orderFactory = $orderFactory;
        $this->entityManager = $entityManager;
        $this->deliveryManager = $deliveryManager;
    }

    public function __invoke(DeliveryQuote $data)
    {
        $delivery = $this->serializer->deserialize($data->getPayload(), Delivery::class, 'jsonld');

        $order = $this->orderFactory->createForDelivery($delivery, $data->getAmount());

        $store = $data->getStore();
        $store->addDelivery($delivery);

        $this->deliveryManager->setDefaults($delivery);

        $this->entityManager->persist($order);

        $data->setDelivery($delivery);
        $data->setState(DeliveryQuote::STATE_CONFIRMED);

        return $data;
    }
}
