<?php

namespace AppBundle\Action;

use AppBundle\Entity\ApiUser;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class Me
{
    use ActionTrait;

    /**
     * @Route(path="/me", name="me",
     *  defaults={
     *   "_api_resource_class"=ApiUser::class,
     *   "_api_collection_operation_name"="me",
     * })
     * @Method("GET")
     */
    public function meAction()
    {
        return $this->getUser();
    }

    /**
     * @Route(
     *     name="me_status",
     *     path="/me/status",
     * )
     * @Method("GET")
     */
    public function statusAction()
    {
        $user = $this->getUser();

        $status = 'AVAILABLE';

        // Check if courier has accepted a delivery previously
        $delivery = $this->deliveryRepository->findOneBy([
            'courier' => $user,
            'status' => ['DISPATCHED', 'PICKED'],
        ]);

        if (null !== $delivery) {
            $status = 'DELIVERING';
        }

        $data = [
            'status' => $status,
        ];

        if (null !== $delivery) {
            $data['delivery'] = [
                'id' => $delivery->getId(),
                'status' => $delivery->getStatus(),
                'originAddress' => [
                    'longitude' => $delivery->getOriginAddress()->getGeo()->getLongitude(),
                    'latitude' => $delivery->getOriginAddress()->getGeo()->getLatitude(),
                ],
                'deliveryAddress' => [
                    'longitude' => $delivery->getDeliveryAddress()->getGeo()->getLongitude(),
                    'latitude' => $delivery->getDeliveryAddress()->getGeo()->getLatitude(),
                ],
                'order' => [
                    'id' => $delivery->getOrder()->getId(),
                ],
            ];
        }

        return new JsonResponse($data);
    }
}
