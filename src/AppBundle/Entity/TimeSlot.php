<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;

class TimeSlot
{
    use Timestampable;

    private $id;
    private $name;
    private $choices;

    public function __construct()
    {
    	$this->choices = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChoices()
    {
        $iterator = $this->choices->getIterator();
        $iterator->uasort(function ($a, $b) {

            $date = new \DateTime();

            [ $startA ] = $a->toDateTime($date);
            [ $startB ] = $b->toDateTime($date);

            return ($startA < $startB) ? -1 : 1;
        });

        return new ArrayCollection(iterator_to_array($iterator));
    }

    /**
     * @param mixed $choices
     *
     * @return self
     */
    public function setChoices($choices)
    {
        $this->choices = $choices;

        return $this;
    }

    public function addChoice($choice)
    {
        $choice->setTimeSlot($this);

        $this->choices->add($choice);
    }

    public function removeChoice($choice)
    {
        $this->choices->removeElement($choice);
    }
}
