<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Delay extends Base
{
    /**
     * @Route(
     *     name="order_delay",
     *     path="/orders/{id}/delay",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="delay"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data, Request $request)
    {
        $user = $this->getUser();

        // Only restaurants can refuse orders
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot delay order', $user->getId()));
        }

        $order = $data;

        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $delay = isset($data['delay']) ? $data['delay'] : 10;

        try {
            $this->orderManager->delay($order, $delay);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $order;
    }
}
