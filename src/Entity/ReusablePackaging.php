<?php

namespace AppBundle\Entity;

use Sylius\Component\Inventory\Model\StockableInterface;

class ReusablePackaging implements StockableInterface
{
    private $id;

    protected $restaurant;

    protected $price = 0;

    protected $name;

    protected $onHold = 0;

    protected $onHand = 0;

    protected $tracked = false;

    public function getId()
    {
        return $this->id;
    }

    public function getRestaurant()
    {
        return $this->restaurant;
    }

    public function setRestaurant(LocalBusiness $restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isInStock(): bool
    {
        return 0 < $this->onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnHold(): ?int
    {
        return $this->onHold;
    }

    /**
     * {@inheritdoc}
     */
    public function setOnHold(?int $onHold): void
    {
        $this->onHold = $onHold;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnHand(): ?int
    {
        return $this->onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function setOnHand(?int $onHand): void
    {
        $this->onHand = (0 > $onHand) ? 0 : $onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function isTracked(): bool
    {
        return $this->tracked;
    }

    /**
     * {@inheritdoc}
     */
    public function setTracked(bool $tracked): void
    {
        $this->tracked = $tracked;
    }

    /**
     * {@inheritdoc}
     */
    public function getInventoryName(): ?string
    {
        return $this->getRestaurant()->getName();
    }
}
