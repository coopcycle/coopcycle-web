<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class StripePaymentMethodOutput
{
    public function __construct($id, $expMont, $expYear, $last4, $brand) {
        $this->id = $id;
        $this->expMont = $expMont;
        $this->expYear = $expYear;
        $this->last4 = $last4;
        $this->brand = $brand;
    }

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $expMonth;

    /**
     * @var string
     */
    public $expYear;

    /**
     * @var string
     */
    public $last4;

    /**
     * @var string
     */
    public $brand;
}
