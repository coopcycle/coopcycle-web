<?php

namespace AppBundle\Sylius\Payment;

trait PawapayTrait
{
    public function setPawapayDepositId($id)
    {
        $this->details = array_merge($this->details, [
            'pawapay_deposit_id' => $id
        ]);
    }

    public function setPawapayPaymentPageUrl($url)
    {
        $this->details = array_merge($this->details, [
            'pawapay_payment_page_url' => $url
        ]);
    }
}
