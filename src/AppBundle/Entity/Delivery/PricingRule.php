<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="pricing_rule")
 */
class PricingRule
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
     * @Groups({"original_rules"})
     * @ORM\Column(type="string")
     * @Assert\Type(type="string")
     */
    protected $expression;

    /**
     * @Groups({"original_rules"})
     * @ORM\Column(type="float")
     * @Assert\Type(type="float")
     */
    protected $price;

    /**
     * @Groups({"original_rules"})
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $position;

    /**
     * @ORM\ManyToOne(targetEntity="PricingRuleSet", inversedBy="rules", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    protected $ruleSet;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function setExpression($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    public function getRuleSet()
    {
        return $this->ruleSet;
    }

    public function setRuleSet(PricingRuleSet $ruleSet)
    {
        $this->ruleSet = $ruleSet;

        return $this;
    }

    public function matches(Delivery $delivery, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getExpression(), [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'deliveryAddress' => $delivery->getDeliveryAddress(),
            'vehicle' => $delivery->getVehicle()
        ]);
    }
}
