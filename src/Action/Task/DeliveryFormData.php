<?php

namespace AppBundle\Action\Task;

use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use AppBundle\Service\OrderManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryFormData
{

    public function __construct(
        private readonly NormalizerInterface $normalizer,
        private readonly DeliveryMapper $deliveryMapper,
        private readonly OrderManager $orderManager
    )
    { }

    public function __invoke(Task $data, Request $request)
    {
        $delivery = $data->getDelivery();
        $order = $delivery?->getOrder();

        $price = $order?->getDeliveryPrice();

        $formData = $this->deliveryMapper->map(
            $delivery,
            $order,
            $price instanceof ArbitraryPrice ? $price : null,
            !is_null($order) && $this->orderManager->hasBookmark($order)
        );

        return new JsonResponse($this->normalizer->normalize($formData, 'jsonld', ['groups' => ['delivery', 'address', 'barcode']]));
    }
}
