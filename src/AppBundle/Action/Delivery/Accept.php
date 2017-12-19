<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Delivery;
use AppBundle\Exception\InvalidStatusException;
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
        $user = $this->getUser();
        $delivery = $data;

        try {
            $this->deliveryManager->dispatch($delivery, $user);
        } catch (InvalidStatusException $e) {
            // Make sure delivery is not in the Redis queue anymore
            // This MAY happen if some user accepted the delivery and has been disconnected from the WebSocket server
            $this->redis->lrem('deliveries:waiting', 0, $delivery->getId());
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $this->redis->lrem('deliveries:dispatching', 0, $delivery->getId());
        $this->redis->hset('deliveries:delivering', 'delivery:'.$delivery->getId(), 'courier:'.$user->getId());

        // FIXME This channel name is not really explicit
        $this->redis->publish('couriers', $user->getId());

        return $delivery;
    }
}
