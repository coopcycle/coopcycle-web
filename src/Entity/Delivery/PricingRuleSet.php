<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints as Assert;

class PricingRuleSet
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @Assert\Valid()
     */
    protected $rules;

    protected $name;

    protected $strategy = 'find';

    public function __construct()
    {
        $this->rules = new ArrayCollection();
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

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function setRules($rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param mixed $strategy
     *
     * @return self
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }
}
