<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class StripePaymentMethodOutput
{
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * @Groups({"order"})
     */
    public function getId()
    {
        return $this->data->id;
    }

    public function getExpMonth()
    {
        return $this->data->expMonth;
    }

    public function getExpYear()
    {
        return $this->data->expYear;
    }

    public function getLast4()
    {
        return $this->data->last4;
    }

    public function getBrand()
    {
        return $this->data->brand;
    }
}
