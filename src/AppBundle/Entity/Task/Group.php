<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use Symfony\Component\Validator\Constraints as Assert;

class Group implements TaggableInterface
{
    use TaggableTrait;

    protected $id;

    /**
     * @Assert\Type(type="string")
     */
    protected $name;

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
}
