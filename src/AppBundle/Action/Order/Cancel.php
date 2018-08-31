<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Cancel extends Base
{
    /**
     * @Route(
     *     name="order_cancel",
     *     path="/orders/{id}/cancel",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="cancel"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data, Request $request)
    {
        $user = $this->getUser();

        // Only restaurants can cancel orders
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot cancel order', $user->getId()));
        }

        $order = $data;

        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        $reason = isset($body['reason']) ? $body['reason'] : null;

        try {
            $this->orderManager->cancel($order, $reason);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $order;
    }
}
