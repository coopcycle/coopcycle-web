<?php

namespace AppBundle\Action;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class Me
{
    use OrderActionTrait;

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

        // Check if courier has accepted an order previously
        $order = $this->orderRepository->findOneBy([
            'courier' => $user,
            'status' => ['ACCEPTED', 'PICKED'],
        ]);

        if (null !== $order) {
            $status = 'DELIVERING';
        }

        $data = [
            'status' => $status,
        ];

        if (null !== $order) {
            $data['order'] = [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'restaurant' => [
                    'longitude' => $order->getDelivery()->getOriginAddress()->getGeo()->getLongitude(),
                    'latitude' => $order->getDelivery()->getOriginAddress()->getGeo()->getLatitude(),
                ],
                'deliveryAddress' => [
                    'longitude' => $order->getDelivery()->getDeliveryAddress()->getGeo()->getLongitude(),
                    'latitude' => $order->getDelivery()->getDeliveryAddress()->getGeo()->getLatitude(),
                ],
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route(
     *     name="me_order",
     *     path="/me/order"
     * )
     * @Method("GET")
     */
    public function orderAction()
    {
        $user = $this->getUser();
        $orderId = $this->redis->get('Courier:'.$user->getId().':order');

        if ($orderId) {
            $order = $this->orderRepository->find($orderId);
            return new Response(
                $this->serializer->serialize($order, 'jsonld')
            );
        }

        return new JsonResponse(null);
    }
}
