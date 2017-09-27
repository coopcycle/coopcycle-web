<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Delivery;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Accept
{
    use ActionTrait;

    /**
     * @Route(
     *     name="delivery_accept",
     *     path="/deliveries/{id}/accept",
     *     defaults={"_api_resource_class"=Delivery::class, "_api_item_operation_name"="accept"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $this->verifyRole('ROLE_COURIER', 'User #%d cannot accept delivery');

        $user = $this->getUser();
        $delivery = $data;

        // Delivery MUST have status = WAITING
        if ($delivery->getStatus() !== Delivery::STATUS_WAITING) {

            // Make sure delivery is not in the Redis queue anymore
            // This MAY happen if some user accepted the delivery and has been disconnected from the WebSocket server
            $this->redis->lrem('deliveries:waiting', 0, $delivery->getId());

            throw new BadRequestHttpException(sprintf('Delivery #%d cannot be accepted anymore', $delivery->getId()));
        }

        $delivery->setCourier($user);
        $delivery->setStatus(Delivery::STATUS_DISPATCHED);

        $this->redis->lrem('deliveries:dispatching', 0, $delivery->getId());
        $this->redis->hset('deliveries:delivering', 'delivery:'.$delivery->getId(), 'courier:'.$user->getId());

        // FIXME This channel name is not really explicit
        $this->redis->publish('couriers', $user->getId());

        return $delivery;
    }
}
