<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use AppBundle\Api\State\EvaluatePricingRuleProcessor;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\ExpressionLanguage\PriceEvaluation;
use AppBundle\Validator\Constraints\PricingRule as AssertPricingRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(),
        new Post(
            uriTemplate: '/pricing_rules/{id}/evaluate',
            status: 200,
            openapiContext: ['summary' => 'Evaluates a PricingRule'],
            denormalizationContext: ['groups' => ['delivery_create', 'pricing_deliveries']],
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_STORE\')',
            input: DeliveryInputDto::class,
            output: YesNoOutput::class,
            processor: EvaluatePricingRuleProcessor::class
        )
    ]
)]
#[AssertPricingRule]
class PricingRule
{
    /**
     * @var int
     */
    #[Groups(['pricing_rule_set:read'])]
    protected $id;

    const TARGET_DELIVERY = 'DELIVERY';
    const TARGET_TASK = 'TASK';
    /**
     * Backward compatibility with legacy logic
     * if strategy is 'map' the rule is
     * applied per delivery/order in standard (one pickup, one dropoff) deliveries
     * applied per task/point in multi-point deliveries
     * @deprecated
     */
    const LEGACY_TARGET_DYNAMIC = 'LEGACY_TARGET_DYNAMIC';

    #[Groups(['pricing_deliveries', 'pricing_rule_set:read', 'pricing_rule_set:write'])]
    #[Assert\Choice(choices: ["DELIVERY", "TASK", "LEGACY_TARGET_DYNAMIC"])]
    protected string $target = self::TARGET_DELIVERY;

    #[Groups(['original_rules', 'pricing_deliveries', 'pricing_rule_set:read', 'pricing_rule_set:write'])]
    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank]
    protected $expression;

    #[Groups(['original_rules', 'pricing_deliveries', 'pricing_rule_set:read', 'pricing_rule_set:write'])]
    #[Assert\Type(type: 'string')]
    protected $price;

    #[Groups(['original_rules', 'pricing_deliveries', 'pricing_rule_set:read', 'pricing_rule_set:write'])]
    protected $position;

    protected $ruleSet;

    /**
     * Temporary storage for name during processing
     * @var string|null
     */
    protected $nameInput;

    /**
     * Each PricingRule can have zero, one, or many ProductOptionValues.
     * @var Collection<int, ProductOptionValue>
     */
    protected $productOptionValues;

    public function __construct()
    {
        $this->productOptionValues = new ArrayCollection();
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

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): void
    {
        $this->target = $target;
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

    /**
     * @return Collection<int, ProductOptionValue>
     */
    public function getProductOptionValues(): Collection
    {
        return $this->productOptionValues;
    }

    public function addProductOptionValue(ProductOptionValue $productOptionValue): self
    {
        if (!$this->productOptionValues->contains($productOptionValue)) {
            $this->productOptionValues->add($productOptionValue);
            $productOptionValue->setPricingRule($this);
        }

        return $this;
    }

    public function removeProductOptionValue(ProductOptionValue $productOptionValue): self
    {
        if ($this->productOptionValues->removeElement($productOptionValue)) {
            // set the owning side to null (unless already changed)
            if ($productOptionValue->getPricingRule() === $this) {
                $productOptionValue->setPricingRule(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getNameInput(): ?string
    {
        return $this->nameInput;
    }

    #[Groups(['pricing_rule_set:write'])]
    #[SerializedName("name")]
    public function setNameInput(?string $nameInput): self
    {
        $this->nameInput = $nameInput;

        return $this;
    }

    #[Groups(['pricing_deliveries', 'pricing_rule_set:read'])]
    public function getName(): ?string
    {
        // Return the value of the first ProductOptionValue if any exists
        $productOptionValue = $this->productOptionValues->first();
        return $productOptionValue instanceof ProductOptionValue ? $productOptionValue->getValue() : null;
    }

    public function matches(array $values, ?ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getExpression(), $values);
    }

    /**
     * @return int|PriceEvaluation|PriceEvaluation[]
     */
    public function apply(array $values, ?ExpressionLanguage $language = null): int|PriceEvaluation|array
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $priceExpression = $this->getPrice();

        return $language->evaluate($priceExpression, $values);
    }

    public function isManualSupplement()
    {
        return $this->getExpression() === 'false';
    }
}
