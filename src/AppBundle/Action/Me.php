<?php

namespace AppBundle\Action;

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
     * @Route(
     *     name="me",
     *     path="/me",
     * )
     * @Method("GET")
     */
    public function meAction()
    {
        $user = $this->getUser();

        $data = $this->serializer->normalize($user);

        // FIXME Exclude those fields in a clean way, using groups
        unset(
            $data['plainPassword'],
            $data['passwordRequestedAt'],
            $data['password'],
            $data['salt'],
            $data['superAdmin'],
            $data['roles'],
            $data['confirmationToken'],
            $data['accountNonExpired'],
            $data['accountNonLocked'],
            $data['credentialsNonExpired'],
            $data['enabled'],
            $data['groups'],
            $data['groupNames']
        );

        if ($user->hasRole('ROLE_CUSTOMER')) {
            $deliveryAddressRepository = $this->doctrine
                ->getManagerForClass('AppBundle:DeliveryAddress')
                ->getRepository('AppBundle:DeliveryAddress');

            $deliveryAddresses = $deliveryAddressRepository->findBy(['customer' => $user]);
            $data['deliveryAddresses'] = $this->serializer->normalize($deliveryAddresses);
        }

        return new JsonResponse($data);
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
                    'longitude' => $order->getRestaurant()->getGeo()->getLongitude(),
                    'latitude' => $order->getRestaurant()->getGeo()->getLatitude(),

                ],
                'deliveryAddress' => [
                    'longitude' => $order->getDeliveryAddress()->getGeo()->getLongitude(),
                    'latitude' => $order->getDeliveryAddress()->getGeo()->getLatitude(),
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