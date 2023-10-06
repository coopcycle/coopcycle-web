<?php

namespace AppBundle\Entity\Delivery;

use Doctrine\Common\Collections\ArrayCollection;

class FailureReasonSet
{

    /**
     * @var int
     */
    private $id;

    private $name;

    private $reasons;

    public function __construct()
    {
        $this->reasons = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): FailureReasonSet
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): FailureReasonSet
    {
        $this->name = $name;
        return $this;
    }

    public function getReasons()
    {
        return $this->reasons;
    }

    public function setReasons($reasons)
    {
        $this->reasons = $reasons;
        return $this;
    }

}
