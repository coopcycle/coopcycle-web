<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class TaskFieldGroup
{
    private $id;

    /**
     * @var string
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
     * @return string
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param string $fields
     *
     * @return self
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
