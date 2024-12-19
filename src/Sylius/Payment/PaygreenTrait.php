<?php

namespace AppBundle\Sylius\Payment;

trait PaygreenTrait
{
    public function setPaygreenPaymentOrderId(string $paymentOrderId)
    {
        $this->details = array_merge($this->details, ['paygreen_payment_order_id' => $paymentOrderId]);
    }

    public function getPaygreenPaymentOrderId(): ?string
    {
        if (isset($this->details['paygreen_payment_order_id'])) {
            return $this->details['paygreen_payment_order_id'];
        }

        return null;
    }

    public function hasPaygreenPaymentOrderId(): bool
    {
        return isset($this->details['paygreen_payment_order_id']);
    }

    public function setPaygreenObjectSecret(string $objectSecret)
    {
        $this->details = array_merge($this->details, ['paygreen_object_secret' => $objectSecret]);
    }

    public function getPaygreenObjectSecret(): ?string
    {
        if (isset($this->details['paygreen_object_secret'])) {
            return $this->details['paygreen_object_secret'];
        }

        return null;
    }
}
