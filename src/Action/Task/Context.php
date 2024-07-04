<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class Context
{

    public function __construct(
        private NormalizerInterface $normalizer,
    )
    { }

    public function __invoke(Task $data, Request $request)
    {
        $delivery = $this->normalizer->normalize($data->getDelivery(), null, ['groups' => 'delivery']);
        $order = $this->normalizer->normalize($data->getDelivery()?->getOrder(), null, ['groups' => 'order']);

        return new JsonResponse([
            'delivery' => $delivery,
            'order' => $order
        ]);
    }
}
