<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

interface ShippingOptionsInterface
{
    /**
     * @return int
     */
    public function getShippingOptionsDays();

    /**
     * @return string
     */
    public function getDeliveryPerimeterExpression();

    /**
     * @param Address $address
     * @param int $distance
     * @param ExpressionLanguage|null $language
     *
     * @return bool
     */
    public function canDeliverAddress(Address $address, $distance, ExpressionLanguage $language = null);
}
