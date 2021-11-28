<?php

namespace AppBundle\Sylius\Payment;

trait MercadopagoTrait
{
    public function setMercadopagoPaymentMethod($paymentMethod)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_payment_method' => $paymentMethod
        ]);
    }

    public function getMercadopagoPaymentMethod()
    {
        if (isset($this->details['mercadopago_payment_method'])) {

            return $this->details['mercadopago_payment_method'];
        }
    }

    public function setMercadopagoInstallments($installments)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_installments' => $installments
        ]);
    }

    public function getMercadopagoInstallments()
    {
        if (isset($this->details['mercadopago_installments'])) {

            return $this->details['mercadopago_installments'];
        }
    }

    public function setMercadopagoPaymentId($paymentId)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_payment_id' => $paymentId
        ]);
    }

    public function getMercadopagoPaymentId()
    {
        if (isset($this->details['mercadopago_payment_id'])) {

            return $this->details['mercadopago_payment_id'];
        }
    }

    public function setMercadopagoPaymentStatus($paymentStatus)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_payment_status' => $paymentStatus
        ]);
    }

    public function getMercadopagoPaymentStatus()
    {
        if (isset($this->details['mercadopago_payment_status'])) {

            return $this->details['mercadopago_payment_status'];
        }
    }
}
