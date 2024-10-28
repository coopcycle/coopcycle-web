<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Payment\EdenredTrait;
use AppBundle\Sylius\Payment\MercadopagoTrait;
use AppBundle\Sylius\Payment\RefundTrait;
use AppBundle\Sylius\Payment\StripeTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Payment\Model\Payment as BasePayment;

class Payment extends BasePayment implements OrderAwareInterface
{
    use StripeTrait;
    use MercadopagoTrait;
    use RefundTrait;
    use EdenredTrait;

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

    /**
     * Determines if the payment is a meal voucher "complement",
     * i.e to pay the remaining amount that can't be paid via meal vouchers.
     */
    public function isMealVoucherComplement(): bool
    {
        if ($this->getMethod()?->getCode() === 'CARD') {
            $edenredPayments = $this->getOrder()->getPayments()->filter(function (self $payment): bool {
                return $payment->getMethod()->getCode() === 'EDENRED' && $payment->getState() === self::STATE_CART;
            });

            return count($edenredPayments) > 0;
        }

        return false;
    }
}
