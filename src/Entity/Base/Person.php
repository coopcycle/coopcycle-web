<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A person (alive, dead, undead, or fictional).
 *
 * @see http://schema.org/Person Documentation on Schema.org
 */
abstract class Person
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var \DateTime Date of birth.
     *
     * @Groups({"person"})
     * @Assert\Date
     * @ApiProperty(iri="https://schema.org/birthDate")
     */
    private $birthDate;

    /**
     * @var string Family name. In the U.S., the last name of an Person. This can be used along with givenName instead of the name property.
     *
     * @Groups({"person"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    private $familyName;

    /**
     * @var string Gender of the person.
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/gender")
     */
    private $gender;

    /**
     * @var string The name of the item.
     *
     * @Groups({"person"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/name")
     */
    private $name;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets birthDate.
     *
     * @param \DateTime $birthDate
     *
     * @return $this
     */
    public function setBirthDate(\DateTime $birthDate = null)
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    /**
     * Gets birthDate.
     *
     * @return \DateTime
     */
    public function getBirthDate()
    {
        return $this->birthDate;
    }

    /**
     * Sets familyName.
     *
     * @param string $familyName
     *
     * @return $this
     */
    public function setFamilyName($familyName)
    {
        $this->familyName = $familyName;

        return $this;
    }

    /**
     * Gets familyName.
     *
     * @return string
     */
    public function getFamilyName()
    {
        return $this->familyName;
    }

    /**
     * Sets gender.
     *
     * @param string $gender
     *
     * @return $this
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * Gets gender.
     *
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
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
}
