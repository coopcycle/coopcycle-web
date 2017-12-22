<?php

namespace AppBundle\Entity\Base;

use AppBundle\Utils\TimeRange;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A particular physical business or branch of an organization. Examples of LocalBusiness include a restaurant, a particular branch of a restaurant chain, a branch of a bank, a medical practice, a club, a bowling alley, etc.
 *
 * @see http://schema.org/LocalBusiness Documentation on Schema.org
 *
 * @ORM\MappedSuperclass
 */
abstract class LocalBusiness
{
    /**
     * @var string The official name of the organization, e.g. the registered company name.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $legalName;

    /**
     * @var string The opening hours for a business. Opening hours can be specified as a weekly time range, starting with days, then times per day. Multiple days can be listed with commas ',' separating each day. Day or time ranges are specified using a hyphen '-'.
     *             - Days are specified using the following two-letter combinations: `Mo`, `Tu`, `We`, `Th`, `Fr`, `Sa`, `Su`.
     *             - Times are specified using 24:00 time. For example, 3pm is specified as `15:00`.
     *             - Here is an example: `<time itemprop="openingHours" datetime="Tu,Th 16:00-20:00">Tuesdays and Thursdays 4-8pm</time>`.
     *             - If a business is open 7 days a week, then it can be specified as `<time itemprop="openingHours" datetime="Mo-Su">Monday through Sunday, all day</time>`.
     *
     * @ORM\Column(type="json_array", nullable=true)
     * @ApiProperty(iri="https://schema.org/openingHours")
     * @Groups({"restaurant"})
     */
    protected $openingHours;

    /**
     * @var string The telephone number.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     */
    protected $telephone;

    /**
     * @var string The Value-added Tax ID of the organization or person.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $vatID;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $additionalProperties = [];

    public function getLegalName()
    {
        return $this->legalName;
    }

    public function setLegalName($legalName)
    {
        $this->legalName = $legalName;

        return $this;
    }

    /**
     * Sets openingHours.
     *
     * @param string $openingHours
     *
     * @return $this
     */
    public function setOpeningHours($openingHours)
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    public function addOpeningHour($openingHour)
    {
        $this->openingHours[] = $openingHour;

        return $this;
    }

    /**
     * Gets openingHours.
     *
     * @return string
     */
    public function getOpeningHours()
    {
        return $this->openingHours;
    }

    /**
     * @return boolean
     */
    public function isOpen(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            if ($timeRange->isOpen($now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next date the LocalBusiness will be opened at.
     *
     * @param \DateTime|null $now
     * @return mixed
     */
    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        $dates = [];

        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            $dates[] = $timeRange->getNextOpeningDate($now);
        }

        sort($dates);

        return array_shift($dates);
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
        $propertyValue = [
            'name' => $name,
            'value' => $value,
        ];

        $key = array_search($propertyValue, $this->additionalProperties);

        if (false === $key) {
            $this->addAdditionalProperty($name, $value);
        } else {
            $this->additionalProperties[$key]['value'] = $value;
        }

        return $this;
    }
}
