<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskField
{
    private $id;

    /**
     * @Groups({"task"})
     * @var string
     */
    private $type;

    /**
     * @Groups({"task"})
     * @var string
     */
    private $name;

    /**
     * @Groups({"task"})
     * @var string
     */
    private $label;

    /**
     * @Groups({"task"})
     * @var bool
     */
    private $required = false;

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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     *
     * @return self
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @param bool $required
     *
     * @return self
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }
}
