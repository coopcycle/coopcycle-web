<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ApiResource(
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 *   collectionOperations={
 *     "me"={ "route_name"="me", "normalization_context"={ "groups"={"user", "place"} } }
 *   },
 *   attributes={
 *     "normalization_context"={ "groups"={"user"} }
 *   }
 * )
 */
class ApiUser extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\ManyToMany(targetEntity="Restaurant", cascade={"all"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     */
    private $restaurants;

    /**
     * @ORM\ManyToMany(targetEntity="Address", cascade={"all"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     */
    private $addresses;

    /**
     * @ORM\ManyToMany(targetEntity="StripeParams", cascade={"all"})
     * @ORM\JoinTable(joinColumns={@ORM\JoinColumn(unique=true)})
     */
    private $stripeParams;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->restaurants = new ArrayCollection();
        $this->stripeParams = new ArrayCollection();

        parent::__construct();
    }

    public function setRestaurants($restaurants)
    {
        $this->restaurants = $restaurants;

        return $this;
    }

    public function addRestaurant(Restaurant $restaurant)
    {
        $this->restaurants->add($restaurant);

        return $this;
    }

    public function ownsRestaurant(Restaurant $restaurant)
    {
        return $this->restaurants->contains($restaurant);
    }

    public function getRestaurants()
    {
        return $this->restaurants;
    }

    public function addAddress(Address $addresses)
    {
        $this->addresses->add($addresses);

        return $this;
    }

    public function setAddresses($addresses)
    {
        $this->addresses = $addresses;

        return $this;
    }

    public function getAddresses()
    {
        return $this->addresses;
    }

    public function getStripeParams()
    {
        return count($this->stripeParams) === 0 ? null : $this->stripeParams[0];
    }

    public function setStripeParams(StripeParams $stripeParams)
    {
        $this->stripeParams[0] = $stripeParams;

        return $this;
    }
}
