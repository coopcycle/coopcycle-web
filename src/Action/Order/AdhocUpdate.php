<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Symfony\Component\HttpFoundation\Request;

class AdhocUpdate extends Adhoc
{
    public function __invoke($data, Request $request)
    {
        $order = $this->objectManager->getRepository(Order::class)->find($request->attributes->get('id'));

        unset($data->customer);

        return $this->parseOrderData($data, $order);
    }
}
