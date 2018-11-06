<?php

namespace AppBundle\Entity;

// use ApiPlatform\Core\Annotation\ApiProperty;
// use ApiPlatform\Core\Annotation\ApiResource;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

class Organization
{
    use TimestampableEntity;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string The name of the item.
     *
     * @Assert\Type(type="string")
     */
    private $name;

    /**
     * @var string The name of the item.
     *
     * @Assert\Type(type="string")
     */
    private $slug;

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

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }
}
