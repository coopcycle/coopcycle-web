<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Refuse extends Base
{
    /**
     * @Route(
     *     name="order_refuse",
     *     path="/orders/{id}/refuse",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="refuse"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        // Only restaurants can refuse orders
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot refuse order', $user->getId()));
        }

        $order = $data;

        try {
            $this->orderManager->refuse($order);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $order;
    }
}
