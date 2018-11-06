<?php

namespace AppBundle\Entity;

// use ApiPlatform\Core\Annotation\ApiProperty;
// use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
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

    private $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function getUsers()
    {
        return $this->users;
    }

    public function setUsers($users)
    {
        $this->users = $users;

        return $this;
    }
}
