<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiProperty;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

final class ConfigurePaymentOutput
{
    /**
     * @ApiProperty
     * @Groups({"order_configure_payment"})
     * @var Collection
     */
    public $payments;

    /**
     * @ApiProperty
     * @Groups({"order_configure_payment"})
     * @var string|null
     */
    public $redirectUrl;

    public function __construct(OrderInterface $order)
    {
        $this->payments = $order->getPayments();
        $this->redirectUrl = $this->getRedirectUrl();
    }

    private function getRedirectUrl(): ?string
    {
        foreach ($this->payments as $payment) {
            $paygreenHostedPaymentUrl = $payment->getPaygreenHostedPaymentUrl();
            if (null !== $paygreenHostedPaymentUrl) {
                return $paygreenHostedPaymentUrl;
            }
        }

        return null;
    }
}
