<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use libphonenumber\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

/**
 * The mailing address.
 *
 * @see http://schema.org/PostalAddress Documentation on Schema.org
 */
abstract class PostalAddress
{
    /**
     * @var string The country. For example, USA. You can also provide the two-letter [ISO 3166-1 alpha-2 country code](http://en.wikipedia.org/wiki/ISO_3166-1).
     *
     * @Groups({"address_create"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressCountry")
     */
    protected $addressCountry;

    /**
     * @var string The locality. For example, Mountain View.
     *
     * @Groups({"address_create", "order_update"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $addressLocality;

    /**
     * @var string The region. For example, CA.
     *
     * @Groups({"address_create"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressRegion")
     */
    protected $addressRegion;

    /**
     * @var string The name of the item.
     *
     * @Groups({"address", "address_create", "task_create", "task_edit"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/name")
     */
    private $name;

    /**
     * @var string The postal code. For example, 94043.
     *
     * @Groups({"address_create", "order_update", "task_create", "address_gb"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/postalCode")
     */
    protected $postalCode;

    /**
     * @var string The post office box number for PO box addresses.
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/postOfficeBoxNumber")
     */
    protected $postOfficeBoxNumber;

    /**
     * @var string The street address. For example, 1600 Amphitheatre Pkwy.
     *
     * @Groups({"address", "address_create", "task_create", "task_edit", "order_update", "cart", "delivery_create"})
     * @Assert\Type(type="string")
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/streetAddress")
     */
    protected $streetAddress;

    /**
     * @var PhoneNumber|null
     *
     * @Groups({"address", "address_create", "task_create", "task_edit", "order_update", "cart", "delivery_create"})
     * @ApiProperty(iri="https://schema.org/telephone")
     * @AssertPhoneNumber(groups={"Default", "cart"})
     */
    protected $telephone;

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
     * @return string|null
     */
    public function getStreetAddress()
    {
        return $this->streetAddress;
    }

    public function getTelephone(): ?PhoneNumber
    {
        return $this->telephone;
    }

    /**
     * @param PhoneNumber|null $telephone
     */
    public function setTelephone(?PhoneNumber $telephone = null)
    {
        $this->telephone = $telephone;

        return $this;
    }
}
