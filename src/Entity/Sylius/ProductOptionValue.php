<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Product\Model\ProductOptionValue as BaseProductOptionValue;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

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
     * Each ProductOptionValue can be linked to zero or one PricingRule.
     * @var PricingRule|null
     */
    protected $pricingRule;

    /**
     * A ProductOptionValue may be linked to a Product.
     * When a Product is linked, it allows synchronizing the "enabled" property.
     * i.e when the linked Product is disabled, the ProductOptionValue is disabled as well.
     */
    protected $product = null;

    public function __construct()
    {
        parent::__construct();
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

    public function getPricingRule(): ?PricingRule
    {
        return $this->pricingRule;
    }

    public function setPricingRule(?PricingRule $pricingRule): void
    {
        $this->pricingRule = $pricingRule;
    }

    #[Groups(['product', 'restaurant_menu', 'restaurant_menus'])]
    public function getIdentifier(): string
    {
        return $this->getCode();
    }

    #[Groups(['product', 'restaurant_menu', 'restaurant_menus'])]
    public function getOffers(): array
    {
        $price = 0;
        switch ($this->getOption()->getStrategy()) {
            case ProductOptionInterface::STRATEGY_OPTION_VALUE:
                $price = $this->getPrice();
                break;
        }

        return [
            '@type' => 'Offer',
            'price' => $price,
        ];
    }

    #[Groups(['product', 'restaurant_menu', 'restaurant_menus'])]
    #[SerializedName('name')]
    public function getSerializedName(): string
    {
        return $this->getValue();
    }

    public function getProduct()
    {
        return $this->product;
    }
}
