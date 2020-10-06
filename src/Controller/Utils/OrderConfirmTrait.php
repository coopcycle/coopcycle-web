<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Hashids\Hashids;

trait OrderConfirmTrait
{
    public function redirectToOrderConfirm(OrderInterface $order)
    {
        $hashids = new Hashids($this->getParameter('secret'), 16);

        $this->addFlash('track_goal', true);
        $this->addFlash('reset_session', true);

        return $this->redirectToRoute('order_confirm', [
            'hashid' => $hashids->encode($order->getId()),
        ]);
    }
}
