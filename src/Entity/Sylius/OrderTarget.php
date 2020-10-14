<?php

namespace AppBundle\Entity\Sylius;

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

    public function getAddress()
    {
        if (null !== $this->hub) {
            return $this->hub->getAddress();
        }

        return $this->restaurant->getAddress();
    }

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
