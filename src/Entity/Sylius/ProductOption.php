<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\DataType\NumRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Validator\Constraints\ProductOption as AssertProductOption;
use Sylius\Component\Product\Model\ProductOption as BaseProductOption;

#[ApiResource(operations: [new Get()], normalizationContext: ['groups' => ['product_option']])]
#[AssertProductOption]
#[ApiResource(uriTemplate: '/restaurants/{id}/product_options.{_format}', uriVariables: ['id' => new Link(fromClass: \AppBundle\Entity\LocalBusiness::class, identifiers: ['id'])], status: 200, normalizationContext: ['groups' => ['product_option']], operations: [new GetCollection()])]
class ProductOption extends BaseProductOption implements ProductOptionInterface
{
    /**
     * @var string
     */
    protected $strategy = ProductOptionInterface::STRATEGY_FREE;

    /**
     * @var boolean
     */
    protected $additional = false;

    protected $valuesRange;

    protected $deletedAt;

    protected $restaurant;

    /**
     * {@inheritdoc}
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * {@inheritdoc}
     */
    public function setStrategy(string $strategy): void
    {
        $this->strategy = $strategy;
    }

    /**
     * {@inheritdoc}
     */
    public function setAdditional(bool $additional): void
    {
        $this->additional = $additional;
    }

    /**
     * {@inheritdoc}
     */
    public function isAdditional(): bool
    {
        return $this->additional;
    }

    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function setRestaurant(?LocalBusiness $restaurant)
    {
        $this->restaurant = $restaurant;
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesRange(): ?NumRange
    {
        return $this->valuesRange;
    }

    public function setValuesRange($range)
    {
        $this->valuesRange = $range;

        return $this;
    }
}
