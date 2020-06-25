<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class Tag
{
    protected $id;

    /**
     * @Groups({"task"})
     */
    protected $name;

    /**
     * @Groups({"task"})
     */
    private $slug;

    /**
     * @Groups({"task"})
     * @Assert\NotBlank()
     */
    private $color;

    private $createdAt;

    private $updatedAt;

    public function __construct($slug = null)
    {
        if (null !== $slug) {
            $this->slug = $slug;
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getColor()
    {
        return $this->color;
    }

    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }
}
