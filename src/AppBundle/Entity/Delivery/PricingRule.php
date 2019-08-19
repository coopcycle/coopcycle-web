<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\PricingRule\Evaluate as EvaluateController;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
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
 *       "write"=false
 *     }
 *   }
 * )
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

        return $language->evaluate($this->getPrice(), self::toExpressionLanguageValues($delivery));
    }

    public function matches(Delivery $delivery, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getExpression(), self::toExpressionLanguageValues($delivery));
    }

    private static function createTaskObject(?Task $task)
    {
        $taskObject = new \stdClass();
        if ($task) {
            $taskObject->address = $task->getAddress();
            $taskObject->createdAt = $task->getCreatedAt();
            $taskObject->before = $task->getDoneBefore();
        }

        return $taskObject;
    }

    public static function toExpressionLanguageValues(Delivery $delivery)
    {
        $pickup = self::createTaskObject($delivery->getPickup());
        $dropoff = self::createTaskObject($delivery->getDropoff());

        return [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'vehicle' => $delivery->getVehicle(),
            'pickup' => $pickup,
            'dropoff' => $dropoff,
        ];
    }
}
