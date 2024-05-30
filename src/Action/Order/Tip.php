<?php

namespace AppBundle\Action\Order;

use ApiPlatform\Core\Validator\Exception\ValidationException;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

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

        if (!isset($body['tipAmount']) && !is_numeric($body['tipAmount'])) {
            return throw new BadRequestHttpException();
        }

        $amount = intval($body['tipAmount']);

        $validator = Validation::createValidator();

        $violations = $validator->validate($amount, [
            new Assert\GreaterThanOrEqual(0),
        ]);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        try {
            $data->setTipAmount($amount);

            $this->orderProcessor->process($data);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e);
        }

        return $data;
    }
}
