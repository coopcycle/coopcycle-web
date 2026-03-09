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
use Doctrine\ORM\Mapping as ORM;

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
     * Metadata for storing additional data like linked dish IDs.
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'jsonb', options: ['default' => '{}'])]
    protected $metadata = [];

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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
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
}
