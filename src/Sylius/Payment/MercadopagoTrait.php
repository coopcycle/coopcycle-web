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

    public function setMercadopagoIssuer($issuer)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_issuer' => $issuer
        ]);
    }

    public function getMercadopagoIssuer()
    {
        if (isset($this->details['mercadopago_issuer'])) {

            return $this->details['mercadopago_issuer'];
        }
    }

    public function setMercadopagoPayerEmail($payerEmail)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_payer_email' => $payerEmail
        ]);
    }

    public function getMercadopagoPayerEmail()
    {
        if (isset($this->details['mercadopago_payer_email'])) {

            return $this->details['mercadopago_payer_email'];
        }
    }
}
