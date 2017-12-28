<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="pricing_rule_set")
 */
class PricingRuleSet
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="PricingRule", mappedBy="ruleSet", cascade={"all"})
     * @ORM\OrderBy({"position" = "ASC"})
     */
    protected $rules;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $name;

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
}
