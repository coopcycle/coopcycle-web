<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
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
        EntityManagerInterface $entityManager)
    {
        $this->serializer = $serializer;
        $this->orderFactory = $orderFactory;
        $this->entityManager = $entityManager;
    }

    public function __invoke(DeliveryQuote $data)
    {
        $delivery = $this->serializer->deserialize($data->getPayload(), Delivery::class, 'jsonld');
        $order = $this->orderFactory->createForDelivery($delivery, $data->getAmount());

        $store = $data->getStore();
        $store->addDelivery($delivery);

        $this->entityManager->persist($order);

        $data->setDelivery($delivery);
        $data->setState(DeliveryQuote::STATE_CONFIRMED);

        return $data;
    }
}
