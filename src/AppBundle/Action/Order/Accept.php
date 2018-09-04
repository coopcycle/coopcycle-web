<?php

namespace AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Accept
{
    private $orderManager;

    public function __construct(OrderManager $orderManager)
    {
        $this->orderManager = $orderManager;
    }

    public function __invoke($data)
    {
        try {
            $this->orderManager->accept($data);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $data;
    }
}
