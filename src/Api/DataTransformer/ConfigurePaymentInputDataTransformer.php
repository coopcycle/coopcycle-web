<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\ConfigurePaymentInput;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Order;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

/**
 * @see https://api-platform.com/docs/v2.6/core/dto/#updating-a-resource-with-a-custom-input
 */
class ConfigurePaymentInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private EdenredClient $edenredClient)
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

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $payment->setMethod($paymentMethod);

        switch ($code) {
            case 'EDENRED+CARD':
            case 'EDENRED':
                $breakdown = $this->edenredClient->splitAmounts($order);
                $payment->setAmountBreakdown($breakdown['edenred'], $breakdown['card']);
                break;
            default:
                $payment->clearAmountBreakdown();
                break;
        }

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
