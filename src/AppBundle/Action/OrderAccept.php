<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;

class OrderAccept
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="order_accept",
     *     path="/orders/{id}/accept",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="accept"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        // TODO Check if order is not accepted yet, etc...

        $user = $this->getUser();

        if ($user->hasRole('ROLE_COURIER')) {

            $data->setCourier($user);

            // message.coordinates.latitude, message.coordinates.longitude, 'courier:' + courierID);

            $this->redis->set('Courier:'.$user->getId().':status', 'BUSY');
            $this->redis->set('Courier:'.$user->getId().':order', $data->getId());

            $this->redis->zrem('GeoSet',
                'order:'.$data->getId(),
                'courier:'.$user->getId()
            );

            // $this->redis->geoadd('OrdersPicked',
            //     'order:'.$data->getId(),
            //     'courier:'.$user->getId()
            // );
        }

        return $data;
    }
}