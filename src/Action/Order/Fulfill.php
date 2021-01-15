<?php

namespace AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Fulfill
{
    private $orderManager;

    public function __construct(OrderManager $orderManager)
    {
        $this->orderManager = $orderManager;
    }

    public function __invoke($data, Request $request)
    {
        try {
            $this->orderManager->fulfill($data);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $data;
    }
}
