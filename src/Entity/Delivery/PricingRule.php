<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
// use AppBundle\Action\PricingRule\Evaluate as EvaluateController;
use AppBundle\Api\State\EvaluatePricingRuleProcessor;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Validator\Constraints\PricingRule as AssertPricingRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
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
            input: DeliveryDto::class,
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

    #[Groups(['pricing_deliveries'])]
    #[Assert\Choice(choices: ["DELIVERY", "TASK", "LEGACY_TARGET_DYNAMIC"])]
    protected string $target = self::TARGET_DELIVERY;

    #[Groups(['original_rules', 'pricing_deliveries'])]
    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank]
    protected $expression;

    #[Groups(['original_rules', 'pricing_deliveries'])]
    #[Assert\Type(type: 'string')]
    protected $price;

    #[Groups(['original_rules', 'pricing_deliveries'])]
    protected $position;

    protected $ruleSet;

    /**
     * @var ?ProductOption
     */
    protected $productOption;

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

    public function getProductOption(): ?ProductOption
    {
        return $this->productOption;
    }

    public function setProductOption(?ProductOption $productOption): self
    {
        $this->productOption = $productOption;

        return $this;
    }

    public function matches(array $values, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($this->getExpression(), $values);
    }

    public function apply(array $values, ExpressionLanguage $language = null): ProductOption
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $priceExpression = $this->getPrice();
        $result = $language->evaluate($priceExpression, $values);

        if (str_contains($priceExpression, 'price_percentage')) {
            return new ProductOption(
                $this,
                0,
                $result
            );
        } else {
            return new ProductOption(
                $this,
                $result,
            );
        }
    }
}
