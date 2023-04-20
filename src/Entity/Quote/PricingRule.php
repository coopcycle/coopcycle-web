<?php

namespace AppBundle\Entity\Quote;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\PricingRule\Evaluate as EvaluateController;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Quote;
use AppBundle\Entity\Task;
use AppBundle\Validator\Constraints\PricingRule as AssertPricingRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "evaluate"={
 *       "method"="POST",
 *       "status"=200,
 *       "path"="/pricing_rules/{id}/evaluate",
 *       "controller"=EvaluateController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_STORE')",
 *       "input"=DeliveryInput::class,
 *       "output"=YesNoOutput::class,
 *       "denormalization_context"={"groups"={"delivery_create", "pricing_deliveries"}},
 *       "write"=false,
 *       "openapi_context"={
 *         "summary"="Evaluates a PricingRule",
 *       }
 *     }
 *   }
 * )
 * @AssertPricingRule
 */
class PricingRule
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @Groups({"original_rules"})
     * @Assert\Type(type="string")
     * @Assert\NotBlank()
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

    public function evaluatePrice(Quote $delivery, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getPrice(), Quote::toExpressionLanguageValues($delivery));
    }

    public function matches(Quote $delivery, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getExpression(), Quote::toExpressionLanguageValues($delivery));
    }
}
