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
     * @var string
     * @Groups({"order"})
     */
    public function getId()
    {
        return $this->data->id;
    }

    /**
     * @var string
     */
    public function getExpMonth()
    {
        return $this->data->expMonth;
    }

    /**
     * @var string
     */
    public function getExpYear()
    {
        return $this->data->expYear;
    }

    /**
     * @var string
     */
    public function getLast4()
    {
        return $this->data->last4;
    }

    /**
     * @var string
     */
    public function getBrand()
    {
        return $this->data->brand;
    }
}
