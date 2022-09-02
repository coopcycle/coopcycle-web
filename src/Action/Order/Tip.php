<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Tip
{
    /**
     * @param OrderProcessorInterface $orderProcessor
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private OrderProcessorInterface $orderProcessor,
        private EntityManagerInterface $entityManager
    )
    {}

    public function __invoke(Order $data, Request $request)
    {
        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        $tipAmount = $body['tipAmount'] ?? null;

        try {
            $data->setTipAmount((int)$tipAmount);

            $this->orderProcessor->process($data);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $data;
    }
}
