<?php

namespace AppBundle\Sylius\Payment;

trait MercadopagoTrait
{
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
}
