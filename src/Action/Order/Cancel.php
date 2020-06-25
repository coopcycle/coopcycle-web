<?php

namespace AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Cancel
{
    private $orderManager;

    public function __construct(OrderManager $orderManager)
    {
        $this->orderManager = $orderManager;
    }

    public function __invoke($data, Request $request)
    {
        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        $reason = isset($body['reason']) ? $body['reason'] : null;

        try {
            $this->orderManager->cancel($data, $reason);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $data;
    }
}
