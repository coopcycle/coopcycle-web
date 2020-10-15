<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Utils\OpeningHoursSpecification;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

trait FulfillmentMethodsTrait
{
    /**
     * @Groups({"restaurant"})
     * @Assert\Valid()
     */
    protected $fulfillmentMethods;

    public function getFulfillmentMethods()
    {
        return $this->fulfillmentMethods;
    }

    public function getFulfillmentMethod(string $method)
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod;
            }
        }

        return null;
    }

    public function addFulfillmentMethod($method, $enabled = true)
    {
        $fulfillmentMethod = $this->fulfillmentMethods->filter(function (FulfillmentMethod $fulfillmentMethod) use ($method): bool {
            return $method === $fulfillmentMethod->getType();
        })->first();

        if (!$fulfillmentMethod) {

            $fulfillmentMethod = new FulfillmentMethod();
            $fulfillmentMethod->setType($method);

            $this->fulfillmentMethods->add($fulfillmentMethod);
        }

        $fulfillmentMethod->setEnabled($enabled);
    }

    public function disableFulfillmentMethod($method)
    {
        $fulfillmentMethod = $this->fulfillmentMethods->filter(function (FulfillmentMethod $fulfillmentMethod) use ($method): bool {
            return $method === $fulfillmentMethod->getType();
        })->first();

        if ($fulfillmentMethod) {

            $fulfillmentMethod->setEnabled(false);
        }
    }

    public function getOpeningHours($method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->getOpeningHours();
            }
        }

        return [];
    }

    public function setOpeningHours($openingHours, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {
                $fulfillmentMethod->setOpeningHours($openingHours);

                break;
            }
        }

        return $this;
    }

    public function addOpeningHour($openingHour, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {
                $fulfillmentMethod->addOpeningHour($openingHour);

                break;
            }
        }

        return $this;
    }

    /**
     * @SerializedName("openingHoursSpecification")
     * @Groups({"restaurant", "restaurant_seo"})
     */
    public function getOpeningHoursSpecification()
    {
        return array_map(function (OpeningHoursSpecification $openingHoursSpecification) {
            return $openingHoursSpecification->jsonSerialize();
        }, OpeningHoursSpecification::fromOpeningHours($this->getOpeningHours()));
    }

    public function isFulfillmentMethodEnabled($method)
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->isEnabled();
            }
        }

        return false;
    }

    public function getOpeningHoursBehavior($method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->getOpeningHoursBehavior();
            }
        }

        return 'asap';
    }

    public function setOpeningHoursBehavior($openingHoursBehavior, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->setOpeningHoursBehavior($openingHoursBehavior);
            }
        }
    }
}
