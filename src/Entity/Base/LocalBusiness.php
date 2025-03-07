<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

/**
 * A particular physical business or branch of an organization. Examples of LocalBusiness include a restaurant, a particular branch of a restaurant chain, a branch of a bank, a medical practice, a club, a bowling alley, etc.
 *
 * @see http://schema.org/LocalBusiness Documentation on Schema.org
 */
abstract class LocalBusiness
{
    /**
     * @var string The official name of the organization, e.g. the registered company name.
     */
    protected $legalName;

    /**
     * @var string The telephone number.
     * @Groups({"order", "restaurant"})
     * @AssertPhoneNumber
     */
    protected $telephone;

    /**
     * @var string The Value-added Tax ID of the organization or person.
     */
    protected $vatID;

    protected array $additionalProperties = [];

    public function getLegalName()
    {
        return $this->legalName;
    }

    public function setLegalName($legalName)
    {
        $this->legalName = $legalName;

        return $this;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }

    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getVatID()
    {
        return $this->vatID;
    }

    public function setVatID($vatID)
    {
        $this->vatID = $vatID;

        return $this;
    }

    public function getAdditionalProperties()
    {
        return $this->additionalProperties;
    }

    public function getAdditionalPropertyValue($name)
    {
        foreach ($this->additionalProperties as $additionalProperty) {
            if ($additionalProperty['name'] === $name) {
                return $additionalProperty['value'];
            }
        }
    }

    public function setAdditionalProperties(array $additionalProperties)
    {
        $this->additionalProperties = $additionalProperties;

        return $this;
    }

    public function hasAdditionalProperty($name)
    {
        foreach ($this->additionalProperties as $property) {
            if ($property['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    public function addAdditionalProperty($name, $value)
    {
        $this->additionalProperties[] = [
            'name' => $name,
            'value' => $value,
        ];

        return $this;
    }

    public function setAdditionalProperty($name, $value)
    {
        $found = false;
        foreach ($this->additionalProperties as $index => $property) {
            if ($property['name'] === $name) {
                $this->additionalProperties[$index]['value'] = $value;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->addAdditionalProperty($name, $value);
        }

        return $this;
    }
}
