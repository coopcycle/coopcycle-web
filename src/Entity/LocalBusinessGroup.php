<?php

namespace AppBundle\Entity;

use AppBundle\Entity\LocalBusiness\ClosingRulesTrait;
use AppBundle\Entity\LocalBusiness\FulfillmentMethodsTrait;
use AppBundle\Entity\LocalBusiness\ShippingOptionsTrait;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Symfony\Component\Validator\Constraints as Assert;

abstract class LocalBusinessGroup implements OpenCloseInterface, ToggleableInterface, Vendor
{
    use ClosingRulesTrait;
    use FulfillmentMethodsTrait;
    use ShippingOptionsTrait;
    use OpenCloseTrait;

    use ToggleableTrait;

    protected $id;
    protected $name;
    protected $restaurants;

     /**
     * @var Contract|null
     * @Assert\Valid(groups={"Default", "activable"})
     */
    protected $contract;

    public function __construct()
    {
        $this->restaurants = new ArrayCollection();
        $this->closingRules = new ArrayCollection();

        $this->fulfillmentMethods = new ArrayCollection();
        $this->addFulfillmentMethod('delivery', true);
        $this->addFulfillmentMethod('collection', false);
    }

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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRestaurants()
    {
        return $this->restaurants;
    }

    /**
     * @param mixed $restaurants
     *
     * @return self
     */
    public function setRestaurants($restaurants)
    {
        $this->restaurants = $restaurants;

        return $this;
    }

    /**
     * @param LocalBusiness $restaurant
     * @return bool
     */
    public function hasRestaurant(LocalBusiness $restaurant): bool
    {
        return $this->getRestaurants()->contains($restaurant);
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public abstract function addRestaurant(LocalBusiness $restaurant);

    /**
     * @param LocalBusiness $restaurant
     */
    public abstract function removeRestaurant(LocalBusiness $restaurant): void;

    /**
     * @return Contract
     */
    public function getContract()
    {
        return $this->contract;
    }

    /**
     * @param Contract $contract
     */
    public function setContract(Contract $contract)
    {
        $this->contract = $contract;
    }

    /**
     * @return array
     */
    public function getBusinessTypes(): array
    {
        $types = [];
        foreach ($this->getRestaurants() as $restaurant) {
            $types[] = $restaurant->getType();
        }

        return array_unique($types);
    }

    public function getOwners(): Collection
    {
        $owners = new ArrayCollection();
        foreach ($this->getRestaurants() as $restaurant) {
            foreach ($restaurant->getOwners() as $owner) {
                $owners->add($owner);
            }

        }

        return $owners;
    }
}
