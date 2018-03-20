<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
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
 * @UniqueEntity("email")
 * @UniqueEntity("username")
 */
class ApiUser extends BaseUser
{
    protected $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="3", max="15")
     * @Assert\Regex(pattern="/^[a-zA-Z0-9_]{3,15}$/")
     * @var string
     */
    protected $username;

    /**
     * @Assert\NotBlank()
     * @var string
     */
    protected $email;

    /**
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/givenName")
    */
    protected $givenName;

    /**
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    protected $familyName;

    /**
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/telephone")
     */
    protected $telephone;

    private $restaurants;

    private $stores;

    private $addresses;

    private $stripeParams;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->restaurants = new ArrayCollection();
        $this->stores = new ArrayCollection();
        $this->stripeParams = new ArrayCollection();

        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function getGivenName()
    {
        return $this->givenName;
    }

    /**
     * @param mixed $givenName
     */
    public function setGivenName($givenName)
    {
        $this->givenName = $givenName;
    }

    /**
     * @return mixed
     */
    public function getFamilyName()
    {
        return $this->familyName;
    }

    /**
     * @param mixed $familyName
     */
    public function setFamilyName($familyName)
    {
        $this->familyName = $familyName;
    }

    /**
     * @return mixed
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param mixed $telephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
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

    public function setStores($stores)
    {
        $this->stores = $stores;

        return $this;
    }

    public function addStore(Store $store)
    {
        $this->stores->add($store);

        return $this;
    }

    public function ownsStore(Store $store)
    {
        return $this->stores->contains($store);
    }

    public function getStores()
    {
        return $this->stores;
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

    public function getFullName() {
        return join(' ', [$this->givenName, $this->familyName]);
    }
}
