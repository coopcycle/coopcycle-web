<?php

namespace AppBundle\Payment;

class MercadopagoPreferenceResponse
{
    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function id()
    {
        return $this->data->id;
    }
}
