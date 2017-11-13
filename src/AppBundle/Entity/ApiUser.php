<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @Assert\NotBlank()
     * @ORM\Column(type="string", nullable=true)
     * @ApiProperty(iri="https://schema.org/givenName")
    */
    protected $givenName;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string", nullable=true)
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    protected $familyName;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="phone_number", nullable=true)
     * @ApiProperty(iri="https://schema.org/telephone")
     */
    protected $telephone;


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
