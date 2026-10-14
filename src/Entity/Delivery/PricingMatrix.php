<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\State\PricingMatrixProcessor;
use AppBundle\Api\State\PricingMatrixRemoveProcessor;
use AppBundle\Pricing\Matrix\MatrixAxis;
use AppBundle\Pricing\Matrix\MatrixAxisEntry;
use AppBundle\Validator\Constraints\PricingMatrix as AssertPricingMatrix;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A grid of prices: one variable per axis, one price per cell.
 *
 * A matrix does not take part in the price calculation itself. It is an editing layer
 * over ordinary pricing rules: PricingMatrixRuleGenerator turns each cell into a rule
 * of the rule set, and those rules are what gets evaluated.
 */
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['pricing_matrix:read']],
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['pricing_matrix:read']],
        ),
        new Post(
            normalizationContext: ['groups' => ['pricing_matrix:read']],
            denormalizationContext: ['groups' => ['pricing_matrix:write']],
            processor: PricingMatrixProcessor::class,
        ),
        new Put(
            normalizationContext: ['groups' => ['pricing_matrix:read']],
            denormalizationContext: ['groups' => ['pricing_matrix:write']],
            processor: PricingMatrixProcessor::class,
        ),
        new Delete(
            processor: PricingMatrixRemoveProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_ADMIN')"
)]
#[AssertPricingMatrix]
class PricingMatrix
{
    /**
     * Null until the matrix has been persisted.
     *
     * @var int|null
     */
    #[Groups(['pricing_matrix:read', 'pricing_rule_set:read'])]
    protected $id;

    #[Groups(['pricing_matrix:read', 'pricing_matrix:write'])]
    #[Assert\NotNull]
    protected ?PricingRuleSet $ruleSet = null;

    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
    protected ?string $name = null;

    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
    #[Assert\Choice(choices: [PricingRule::TARGET_DELIVERY, PricingRule::TARGET_TASK])]
    protected string $target = PricingRule::TARGET_TASK;

    /**
     * For a per-point matrix: restricts the matrix to pickups or to dropoffs.
     * Null means it applies to every point.
     */
    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
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

    /**
     * The axes are exposed as the raw arrays they are stored as: MatrixAxis is the
     * typed view used by the generator, not a serialization format.
     */
    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
    #[SerializedName('rowAxis')]
    public function getRowAxisArray(): array
    {
        return $this->rowAxis;
    }

    public function setRowAxisArray(array $rowAxis): self
    {
        $this->rowAxis = $rowAxis;

        return $this;
    }

    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
    #[SerializedName('columnAxis')]
    public function getColumnAxisArray(): array
    {
        return $this->columnAxis;
    }

    public function setColumnAxisArray(array $columnAxis): self
    {
        $this->columnAxis = $columnAxis;

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
    #[Groups(['pricing_matrix:read', 'pricing_matrix:write', 'pricing_rule_set:read'])]
    public function getCells(): array
    {
        return $this->cells;
    }

    public function setCells(array $cells): self
    {
        $this->cells = $cells;

        return $this;
    }

    /**
     * A copy of this grid, without the rules it generated: those are generated again
     * for whichever rule set the copy ends up in. Entry keys are kept, being unique
     * within a grid rather than across them.
     */
    public function duplicate(): self
    {
        $matrix = new self();

        $matrix->setName($this->name);
        $matrix->setTarget($this->target);
        $matrix->setTaskType($this->taskType);
        $matrix->setRowAxisArray($this->rowAxis);
        $matrix->setColumnAxisArray($this->columnAxis);
        $matrix->setCells($this->cells);

        return $matrix;
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
