<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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
     * @ORM\Column(type="string")
     * @Assert\Type(type="string")
     */
    protected $expression;

    /**
     * @ORM\Column(type="float")
     * @Assert\Type(type="float")
     */
    protected $price;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $position;

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

    public function matches(Delivery $delivery)
    {
        $language = new ExpressionLanguage();

        return $language->evaluate($this->getExpression(), [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
        ]);
    }
}
