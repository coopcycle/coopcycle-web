<?php

namespace AppBundle\Entity\Model;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait NameTrait
{
    /**
     * @var string The name of the item
     *
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"order"})
     */
    protected $name;

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
