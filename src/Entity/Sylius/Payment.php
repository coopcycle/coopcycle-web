<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\PaymentRefundInput;
use AppBundle\Api\State\CreatePaymentRefundProcessor;
use AppBundle\Sylius\Payment\EdenredTrait;
use AppBundle\Sylius\Payment\GiftCardTrait;
use AppBundle\Sylius\Payment\MealVoucherPaymentMethods;
use AppBundle\Sylius\Payment\MercadopagoTrait;
use AppBundle\Sylius\Payment\PaygreenTrait;
use AppBundle\Sylius\Payment\RefundTrait;
use AppBundle\Sylius\Payment\StripeTrait;
use AppBundle\Sylius\Payment\PawapayTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Payment\Model\Payment as BasePayment;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_DISPATCHER")'),
        new Post(
            uriTemplate: '/payments/{id}/refunds',
            processor: CreatePaymentRefundProcessor::class,
            input: PaymentRefundInput::class,
            openapiContext: ['summary' => 'Adds a refunds to a Payment resource.'],
            security: 'is_granted("ROLE_DISPATCHER")',
            denormalizationContext: ['groups' => ['payment_refund']],
        ),
    ],
    normalizationContext: ['groups' => ['payment']]
)]
class Payment extends BasePayment implements OrderAwareInterface
{
    use StripeTrait;
    use MercadopagoTrait;
    use PaygreenTrait;
    use RefundTrait;
    use EdenredTrait;
    use GiftCardTrait;
    use PawapayTrait;

    protected $order;
    protected $refunds;

    public function __construct()
    {
        $this->refunds = new ArrayCollection();

        parent::__construct();
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    /**
     * @deprecated
     */
    public function isCashOnDelivery(): bool
    {
        $method = $this->getMethod();

        return null !== $method && $method->getCode() === 'CASH_ON_DELIVERY';
    }

    public function isOffline(): bool
    {
        $method = $this->getMethod();

        return null !== $method && $method->getCode() === 'CASH_ON_DELIVERY';
    }

    public function isGiftCard(): bool
    {
        return $this->getMethod()?->getCode() === 'GIFT_CARD';
    }

    /**
     * Whether this card payment is only covering what something else
     * couldn't: the rest of an order already part-paid by a meal voucher or
     * a gift card.
     *
     * It matters to StripeManager, which must not put a platform fee or a
     * Connect transfer on a charge that is a fraction of the order -- Stripe
     * rejects a fee larger than the charge, and a transfer computed from the
     * order total exceeds what was actually taken.
     *
     * Replaces isMealVoucherComplement(), which hardcoded EDENRED and only
     * looked at payments in `cart` state. The state filter is widened to
     * "not cancelled or failed" because a retried checkout leaves dead legs
     * behind, and a dead leg is not something this payment is complementing.
     *
     * Note this is *not* the question to ask about gift-card orders in
     * general: a card covering the whole order leaves no complement to ask.
     * Use OrderInterface::hasGiftCardPayment() for that.
     */
    public function isComplement(): bool
    {
        if ($this->getMethod()?->getCode() !== 'CARD') {
            return false;
        }

        $codes = array_merge(MealVoucherPaymentMethods::CODES, ['GIFT_CARD']);

        return $this->getOrder()->getPayments()->exists(
            fn ($key, self $payment): bool => $payment !== $this
                && in_array($payment->getMethod()?->getCode(), $codes, true)
                && !in_array($payment->getState(), [self::STATE_CANCELLED, self::STATE_FAILED], true)
        );
    }
}
