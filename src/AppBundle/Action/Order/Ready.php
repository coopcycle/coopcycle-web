<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Ready extends Base
{
    /**
     * @Route(
     *     name="order_ready",
     *     path="/orders/{id}/ready",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="ready"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        // Only restaurants can set orders as ready
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot set order to ready', $user->getId()));
        }

        $order = $data;

        try {
            $this->orderManager->ready($order);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $order;
    }
}
