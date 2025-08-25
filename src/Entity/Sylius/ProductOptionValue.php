<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Product\Model\ProductOptionValue as BaseProductOptionValue;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(),
        new Put(
            uriTemplate: '/product_option_values/{id}',
            denormalizationContext: ['groups' => ['product_option_value_update']],
            security: 'is_granted(\'edit\', object)'
        )
    ],
    normalizationContext: ['groups' => ['product_option']]
)]
class ProductOptionValue extends BaseProductOptionValue implements ProductOptionValueInterface
{
    use ToggleableTrait;

    /**
     * @var int
     */
    #[Assert\GreaterThanOrEqual(0)]
    protected $price = 0;

    /**
     * We model a many-to-many relationship between ProductOptionValue and PricingRule
     * for backwards compatibility. In most cases, there will be only one PricingRule at most
     * linked to a ProductOptionValue.
     * @var Collection<int, PricingRule>
     */
    protected $pricingRules;

    public function __construct()
    {
        parent::__construct();
        $this->pricingRules = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrice(int $price): void
    {
        $this->price = $price;
    }

    /**
     * @return Collection<int, PricingRule>
     */
    public function getPricingRules(): Collection
    {
        return $this->pricingRules;
    }

    public function getPricingRule(): PricingRule | null
    {
        $pricingRule = $this->pricingRules->first();
        
        if ($pricingRule instanceof PricingRule) {
            return $pricingRule;
        } else {
            return null;
        }
    }

    public function setPricingRule(PricingRule $pricingRule): self
    {
        if (!$this->pricingRules->contains($pricingRule)) {
            $this->pricingRules->add($pricingRule);
            $pricingRule->setProductOptionValue($this);
        }

        return $this;
    }

    public function removePricingRule(PricingRule $pricingRule): self
    {
        if ($this->pricingRules->removeElement($pricingRule)) {
            // set the owning side to null (unless already changed)
            if ($pricingRule->getProductOptionValue() === $this) {
                $pricingRule->setProductOptionValue(null);
            }
        }

        return $this;
    }
}
