<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class PricingRule
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @Groups({"original_rules"})
     * @Assert\Type(type="string")
     */
    protected $expression;

    /**
     * @Groups({"original_rules"})
     * @Assert\Type(type="string")
     */
    protected $price;

    /**
     * @Groups({"original_rules"})
     */
    protected $position;

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

    public function evaluatePrice(Delivery $delivery, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getPrice(), [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'deliveryAddress' => $delivery->getDeliveryAddress(),
            'vehicle' => $delivery->getVehicle()
        ]);
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
