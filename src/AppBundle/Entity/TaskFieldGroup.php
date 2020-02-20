<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;


class TaskFieldGroup
{
    private $id;

    /**
     * @var string
     * @Groups({"task"})
     */
    private $name;

    /**
     * @var string
     */
    private $fields;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @Groups({"task"})
     * @SerializedName("items")
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return self
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
