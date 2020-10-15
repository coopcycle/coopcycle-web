<?php

namespace AppBundle\Entity\LocalBusiness;

interface ShippingOptionsInterface
{
    /**
     * @return int
     */
    public function getOrderingDelayMinutes();

    /**
     * @return int
     */
    public function getShippingOptionsDays();
}
