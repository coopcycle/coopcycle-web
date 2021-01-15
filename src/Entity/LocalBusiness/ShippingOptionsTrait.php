<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use AppBundle\Utils\OpeningHoursSpecification;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

trait ShippingOptionsTrait
{
    /**
     * @Assert\GreaterThan(1)
     * @Assert\LessThanOrEqual(6)
     */
    protected $shippingOptionsDays = 2;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    protected $deliveryPerimeterExpression = 'distance < 3000';

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

    /**
     * @return string
     */
    public function getDeliveryPerimeterExpression()
    {
        return $this->deliveryPerimeterExpression;
    }

    /**
     * @param string $deliveryPerimeterExpression
     */
    public function setDeliveryPerimeterExpression(string $deliveryPerimeterExpression)
    {
        $this->deliveryPerimeterExpression = $deliveryPerimeterExpression;
    }

    public function canDeliverAddress(Address $address, $distance, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $dropoff = new \stdClass();
        $dropoff->address = $address;

        return $language->evaluate($this->deliveryPerimeterExpression, [
            'distance' => $distance,
            'dropoff' => $dropoff,
        ]);
    }
}
