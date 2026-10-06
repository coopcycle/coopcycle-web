<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Pricing\Matrix\MatrixAxis;
use AppBundle\Pricing\Matrix\MatrixAxisEntry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * A grid of prices: one variable per axis, one price per cell.
 *
 * A matrix does not take part in the price calculation itself. It is an editing layer
 * over ordinary pricing rules: PricingMatrixRuleGenerator turns each cell into a rule
 * of the rule set, and those rules are what gets evaluated.
 */
class PricingMatrix
{
    /**
     * @var int
     */
    protected $id;

    protected ?PricingRuleSet $ruleSet = null;

    protected ?string $name = null;

    protected string $target = PricingRule::TARGET_TASK;

    /**
     * For a per-point matrix: restricts the matrix to pickups or to dropoffs.
     * Null means it applies to every point.
     */
    protected ?string $taskType = null;

    protected array $rowAxis = [];

    protected array $columnAxis = [];

    /**
     * Cell prices in cents, keyed by "<rowKey>:<columnKey>".
     * A cell with no entry here generates no rule.
     *
     * @var array<string, int>
     */
    protected array $cells = [];

    /**
     * @var Collection<int, PricingRule>
     */
    protected $rules;

    public function __construct()
    {
        $this->rules = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRuleSet(): ?PricingRuleSet
    {
        return $this->ruleSet;
    }

    public function setRuleSet(?PricingRuleSet $ruleSet): self
    {
        $this->ruleSet = $ruleSet;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getTaskType(): ?string
    {
        return $this->taskType;
    }

    public function setTaskType(?string $taskType): self
    {
        $this->taskType = $taskType;

        return $this;
    }

    public function getRowAxis(): MatrixAxis
    {
        return MatrixAxis::fromArray($this->rowAxis);
    }

    public function setRowAxis(MatrixAxis $axis): self
    {
        $this->rowAxis = $axis->toArray();

        return $this;
    }

    public function getColumnAxis(): MatrixAxis
    {
        return MatrixAxis::fromArray($this->columnAxis);
    }

    public function setColumnAxis(MatrixAxis $axis): self
    {
        $this->columnAxis = $axis->toArray();

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    public function setCells(array $cells): self
    {
        $this->cells = $cells;

        return $this;
    }

    public static function cellKey(MatrixAxisEntry $row, MatrixAxisEntry $column): string
    {
        return sprintf('%s:%s', $row->key, $column->key);
    }

    public function getCellPrice(MatrixAxisEntry $row, MatrixAxisEntry $column): ?int
    {
        $key = self::cellKey($row, $column);

        return isset($this->cells[$key]) ? (int) $this->cells[$key] : null;
    }

    public function setCellPrice(MatrixAxisEntry $row, MatrixAxisEntry $column, ?int $price): self
    {
        $key = self::cellKey($row, $column);

        if (null === $price) {
            unset($this->cells[$key]);
        } else {
            $this->cells[$key] = $price;
        }

        return $this;
    }

    /**
     * @return Collection<int, PricingRule>
     */
    public function getRules()
    {
        return $this->rules;
    }

    public function addRule(PricingRule $rule): self
    {
        if (!$this->rules->contains($rule)) {
            $this->rules->add($rule);
        }

        return $this;
    }

    public function removeRule(PricingRule $rule): self
    {
        $this->rules->removeElement($rule);

        return $this;
    }
}
