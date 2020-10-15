<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Utils\OpeningHoursSpecification;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

trait ShippingOptionsTrait
{
    /**
     * @var integer Additional time to delay ordering
     */
    protected $orderingDelayMinutes = 0;

    /**
     * @Assert\GreaterThan(1)
     * @Assert\LessThanOrEqual(6)
     */
    protected $shippingOptionsDays = 2;

    /**
     * @return int
     */
    public function getOrderingDelayMinutes()
    {
        return $this->orderingDelayMinutes;
    }

    /**
     * @param int $orderingDelayMinutes
     */
    public function setOrderingDelayMinutes(int $orderingDelayMinutes)
    {
        $this->orderingDelayMinutes = $orderingDelayMinutes;
    }

    /**
     * @return int
     */
    public function getShippingOptionsDays()
    {
        return $this->shippingOptionsDays;
    }

    /**
     * @param int $shippingOptionsDays
     */
    public function setShippingOptionsDays(int $shippingOptionsDays)
    {
        $this->shippingOptionsDays = $shippingOptionsDays;
    }
}
