<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Delivery;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Deliver
{
    use ActionTrait;

    /**
     * @Route(
     *     name="delivery_deliver",
     *     path="/deliveries/{id}/deliver",
     *     defaults={"_api_resource_class"=Delivery::class, "_api_item_operation_name"="deliver"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $this->verifyRole('ROLE_COURIER', 'User #%d cannot deliver delivery');

        $delivery = $data;
        $order = $delivery->getOrder();

        // Make sure the courier picking order is authorized
        if ($delivery->getCourier() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $delivery->setStatus(Delivery::STATUS_DELIVERED);

        $this->redis->hdel('deliveries:delivering', 'delivery:'.$order->getId());

        $this->redis->publish('couriers:available', $this->getUser()->getId());

        return $delivery;
    }
}
