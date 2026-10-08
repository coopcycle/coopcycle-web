<?php

declare(strict_types=1);

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A manual supplement added to the orders generated from a recurrence rule
 */
class RecurrenceRuleManualSupplement
{
    /**
     * @var int
     */
    private $id;

    private ?RecurrenceRule $recurrenceRule = null;

    #[Assert\NotNull]
    #[Groups(['task_recurrence_rule'])]
    private ?PricingRule $pricingRule = null;

    #[Assert\NotNull]
    #[Assert\GreaterThan(0)]
    #[Groups(['task_recurrence_rule'])]
    private ?int $quantity = null;

    public static function create(PricingRule $pricingRule, int $quantity): self
    {
        $supplement = new self();
        $supplement->setPricingRule($pricingRule);
        $supplement->setQuantity($quantity);

        return $supplement;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecurrenceRule(): ?RecurrenceRule
    {
        return $this->recurrenceRule;
    }

    public function setRecurrenceRule(?RecurrenceRule $recurrenceRule): void
    {
        $this->recurrenceRule = $recurrenceRule;
    }

    public function getPricingRule(): ?PricingRule
    {
        return $this->pricingRule;
    }

    public function setPricingRule(?PricingRule $pricingRule): void
    {
        $this->pricingRule = $pricingRule;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
