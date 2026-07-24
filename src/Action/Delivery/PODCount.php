<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\DeliveryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns how many deliveries would be included in a proofs of delivery export,
 * so that the UI can display the exact same number as the one exported.
 */
class PODCount extends PODAction
{
    public function __construct(
        private readonly DeliveryRepository $deliveryRepository
    ) { }

    public function __invoke(Request $request): Response
    {
        $params = $request->query;

        $this->validateRequiredParameters($params);

        [$from, $to] = $this->parseDateRange($params);

        return new JsonResponse([
            'deliveries' => $this->deliveryRepository->countDeliveriesWithProofsOfDelivery(
                $this->getStoreId($request),
                $from,
                $to
            ),
        ]);
    }
}
