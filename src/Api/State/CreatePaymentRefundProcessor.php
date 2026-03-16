<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\PaymentRefundInput;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\OrderManager;

class CreatePaymentRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderManager $orderManager,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param PaymentRefundInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Payment */
        $payment = $this->provider->provide($operation, $uriVariables, $context);

        $this->orderManager->refundPayment($payment, $data->amount, $data->liableParty, $data->comments);

        return $this->persistProcessor->process($payment, $operation, $uriVariables, $context);
    }
}
