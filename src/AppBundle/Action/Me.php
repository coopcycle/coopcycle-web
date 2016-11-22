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
     *     name="me_status",
     *     path="/me/status",
     * )
     * @Method("GET")
     */
    public function statusAction()
    {
        $user = $this->getUser();
        $status = $this->redis->get('Courier:'.$user->getId().':status');

        if (!$status) {
            $status = 'AVAILABLE';
        }

        return new JsonResponse(['status' => $status]);
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