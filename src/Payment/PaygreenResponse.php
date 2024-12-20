<?php

namespace AppBundle\Payment;

use Omnipay\Common\Message\AbstractResponse;

class PaygreenResponse extends AbstractResponse
{
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function isSuccessful()
    {
        return isset($this->data->status);
    }
}

