<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\ConfigurePaymentInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

/**
 * @see https://api-platform.com/docs/v2.6/core/dto/#updating-a-resource-with-a-custom-input
 */
class ConfigurePaymentInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentContext $paymentContext,
        private OrderProcessorInterface $orderPaymentProcessor,
        private EntityManagerInterface $entityManager)
    {}

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $order = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        $code = strtoupper($data->paymentMethod);

        $paymentMethod = $this->paymentMethodRepository->findOneByCode($code);
        if (null === $paymentMethod) {
            throw new \Exception(sprintf('Payment method "%s" not found', $code));
        }

        $this->paymentContext->setMethod($code);

        $this->orderPaymentProcessor->process($order);

        $this->entityManager->flush();

        return $order;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Order) {
            return false;
        }

        return $to === Order::class && ($context['input']['class'] ?? null) === ConfigurePaymentInput::class;
    }
}
