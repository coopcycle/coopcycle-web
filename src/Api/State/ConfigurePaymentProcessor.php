<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ConfigurePaymentInput;
use AppBundle\Api\Dto\ConfigurePaymentOutput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

class ConfigurePaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentContext $paymentContext,
        private OrderProcessorInterface $orderPaymentProcessor,
        private ItemProvider $itemProvider,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @param ConfigurePaymentInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $code = strtoupper($data->paymentMethod);

        $paymentMethod = $this->paymentMethodRepository->findOneByCode($code);
        if (null === $paymentMethod) {
            throw new \Exception(sprintf('Payment method "%s" not found', $code));
        }

        $order = $this->itemProvider->provide($operation, $uriVariables, $context);

        $this->paymentContext->setMethod($code);
        $this->orderPaymentProcessor->process($order);

        $order = $this->persistProcessor->process($order, $operation, $uriVariables, $context);

        return new ConfigurePaymentOutput($order);
    }
}
