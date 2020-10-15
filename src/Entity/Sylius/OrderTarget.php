<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\LocalBusiness\ShippingOptionsInterface;

class OrderTarget
{
    private $id;
    private $restaurant;
    private $hub;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getRestaurant()
    {
        return $this->restaurant;
    }

    /**
     * @param mixed $restaurant
     *
     * @return self
     */
    public function setRestaurant($restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getHub()
    {
        return $this->hub;
    }

    /**
     * @param mixed $hub
     *
     * @return self
     */
    public function setHub($hub)
    {
        $this->hub = $hub;

        return $this;
    }

    /* BEGIN Common interface between Restaurant & Hub */

    public function getAddress()
    {
        if (null !== $this->hub) {
            return $this->hub->getAddress();
        }

        return $this->restaurant->getAddress();
    }

    public function getOpeningHours($method = 'delivery')
    {
        if (null !== $this->hub) {
            return $this->hub->getOpeningHours($method);
        }

        return $this->restaurant->getOpeningHours($method);
    }

    public function hasClosingRuleFor(\DateTime $date = null, \DateTime $now = null): bool
    {
        if (null !== $this->hub) {
            return $this->hub->hasClosingRuleFor($date, $now);
        }

        return $this->restaurant->hasClosingRuleFor($date, $now);
    }

    public function isFulfillmentMethodEnabled($method)
    {
        if (null !== $this->hub) {
            return $this->hub->isFulfillmentMethodEnabled($method);
        }

        return $this->restaurant->isFulfillmentMethodEnabled($method);
    }

    public function getFulfillmentMethod(string $method)
    {
        if (null !== $this->hub) {
            return $this->hub->getFulfillmentMethod($method);
        }

        return $this->restaurant->getFulfillmentMethod($method);
    }

    public function getOrderingDelayMinutes()
    {
        if (null !== $this->hub) {
            return $this->hub->getOrderingDelayMinutes();
        }

        return $this->restaurant->getOrderingDelayMinutes();
    }

    public function getShippingOptionsDays()
    {
        if (null !== $this->hub) {
            return $this->hub->getShippingOptionsDays();
        }

        return $this->restaurant->getShippingOptionsDays();
    }

    public function getClosingRules()
    {
        if (null !== $this->hub) {
            return $this->hub->getClosingRules();
        }

        return $this->restaurant->getClosingRules();
    }

    /* END Common interface between Restaurant & Hub */

    public static function withRestaurant($restaurant)
    {
        $target = new self();
        $target->setRestaurant($restaurant);

        return $target;
    }

    public function toArray()
    {
        if (null !== $this->hub) {
            return $this->hub->getRestaurants();
        }

        return [ $this->restaurant ];
    }
}
