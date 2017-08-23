<?php

namespace AppBundle\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The mailing address.
 *
 * @see http://schema.org/PostalAddress Documentation on Schema.org
 *
 * @ORM\MappedSuperclass
 */
abstract class PostalAddress
{
    /**
     * @var string The country. For example, USA. You can also provide the two-letter [ISO 3166-1 alpha-2 country code](http://en.wikipedia.org/wiki/ISO_3166-1).
     *
     * @ORM\Column(nullable=true, options={"default": "FRANCE"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressCountry")
     */
    protected $addressCountry;

    /**
     * @var string The locality. For example, Mountain View.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $addressLocality;

    /**
     * @var string The region. For example, CA.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressRegion")
     */
    protected $addressRegion;

    /**
     * @var string The name of the item.
     *
     * @Groups({"place"})
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/name")
     */
    private $name;

    /**
     * @var string Family name. In the U.S., the last name of an Person. This can be used along with givenName instead of the name property.
     *
     * @Groups({"place"})
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    private $familyName;

    /**
     * @var string The postal code. For example, 94043.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/postalCode")
     */
    protected $postalCode;

    /**
     * @var string The post office box number for PO box addresses.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/postOfficeBoxNumber")
     */
    protected $postOfficeBoxNumber;

    /**
     * @var string The street address. For example, 1600 Amphitheatre Pkwy.
     *
     * @Groups({"place"})
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/streetAddress")
     */
    protected $streetAddress;

    /**
     * @var string Second field for street address, typically additional informations.
     *
     * @Groups({"place"})
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/streetAddress")
     */
    protected $streetAddress2;

    /**
     * @var string Phone Number, mandatory for delivery.
     *
     * @Groups({"place"})
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(groups={"delivery_address"})
     * @ApiProperty(iri="https://schema.org/streetAddress")
     */
    protected $phoneNumber;

    /**
     * Sets addressCountry.
     *
     * @param string $addressCountry
     *
     * @return $this
     */
    public function setAddressCountry($addressCountry)
    {
        $this->addressCountry = $addressCountry;

        return $this;
    }

    /**
     * Gets addressCountry.
     *
     * @return string
     */
    public function getAddressCountry()
    {
        return $this->addressCountry;
    }

    /**
     * Sets addressLocality.
     *
     * @param string $addressLocality
     *
     * @return $this
     */
    public function setAddressLocality($addressLocality)
    {
        $this->addressLocality = $addressLocality;

        return $this;
    }

    /**
     * Gets addressLocality.
     *
     * @return string
     */
    public function getAddressLocality()
    {
        return $this->addressLocality;
    }

    /**
     * Sets addressRegion.
     *
     * @param string $addressRegion
     *
     * @return $this
     */
    public function setAddressRegion($addressRegion)
    {
        $this->addressRegion = $addressRegion;

        return $this;
    }

    /**
     * Gets addressRegion.
     *
     * @return string
     */
    public function getAddressRegion()
    {
        return $this->addressRegion;
    }

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets familyName.
     *
     * @param string $familyName
     *
     * @return $this
     */
    public function setfamilyName($familyName)
    {
        $this->familyName = $familyName;

        return $this;
    }

    /**
     * Gets familyName.
     *
     * @return string
     */
    public function getfamilyName()
    {
        return $this->familyName;
    }

    /**
     * Sets postalCode.
     *
     * @param string $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Gets postalCode.
     *
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * Sets postOfficeBoxNumber.
     *
     * @param string $postOfficeBoxNumber
     *
     * @return $this
     */
    public function setPostOfficeBoxNumber($postOfficeBoxNumber)
    {
        $this->postOfficeBoxNumber = $postOfficeBoxNumber;

        return $this;
    }

    /**
     * Gets postOfficeBoxNumber.
     *
     * @return string
     */
    public function getPostOfficeBoxNumber()
    {
        return $this->postOfficeBoxNumber;
    }

    /**
     * Sets streetAddress.
     *
     * @param string $streetAddress
     *
     * @return $this
     */
    public function setStreetAddress($streetAddress)
    {
        $this->streetAddress = $streetAddress;

        return $this;
    }

    /**
     * Gets streetAddress.
     *
     * @return string
     */
    public function getStreetAddress()
    {
        return $this->streetAddress;
    }

    /**
     * Sets streetAddress2.
     *
     * @param string $streetAddress2
     *
     * @return $this
     */
    public function setStreetAddress2($streetAddress2)
    {
        $this->streetAddress2 = $streetAddress2;

        return $this;
    }

    /**
     * Gets streetAddress2.
     *
     * @return string
     */
    public function getStreetAddress2()
    {
        return $this->streetAddress2;
    }

    /**
     * Sets phoneNumber.
     *
     * @param string $phoneNumber
     *
     * @return $this
     */
    public function setphoneNumber($phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Gets phoneNumber.
     *
     * @return string
     */
    public function getphoneNumber()
    {
        return $this->phoneNumber;
    }
}
