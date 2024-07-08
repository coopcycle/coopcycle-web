<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\EdenredCredentialsInput;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Order;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * @see https://api-platform.com/docs/v2.6/core/dto/#updating-a-resource-with-a-custom-input
 */
class EdenredCredentialsInputDataTransformer implements DataTransformerInterface
{
    public function __construct(private EdenredClient $edenredClient)
    {}

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $order = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        $customer = $order->getCustomer();

        $customer->setEdenredAccessToken($data->accessToken);
        $customer->setEdenredRefreshToken($data->refreshToken);

        // If the current order needs to be paid with Edenred,
        // we setup the amount breakdown
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        if ($payment->isEdenredWithCard()) {
            $breakdown = $this->edenredClient->splitAmounts($order);
            $payment->setAmountBreakdown($breakdown['edenred'], $breakdown['card']);
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

        return $to === Order::class && ($context['input']['class'] ?? null) === EdenredCredentialsInput::class;
    }
}
