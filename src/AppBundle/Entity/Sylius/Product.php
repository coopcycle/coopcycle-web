<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Product\Model\Product as BaseProduct;

/**
 * @ApiResource(
 *   collectionOperations={
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "put"={
 *       "method"="PUT",
 *       "denormalization_context"={"groups"={"product_update"}},
 *       "access_control"="is_granted('ROLE_RESTAURANT') and null != object.getRestaurant() and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"product"}}
 *   }
 * )
 */
class Product extends BaseProduct implements ProductInterface
{
    protected $restaurant;

    public function __construct()
    {
        parent::__construct();

        $this->restaurant = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant->get(0);
    }

    /**
     * {@inheritdoc}
     */
    public function setRestaurant(?Restaurant $restaurant): void
    {
        $this->restaurant->clear();
        $this->restaurant->add($restaurant);
    }

    public function getAllergens()
    {
        $attributeValue = $this->getAttributeByCodeAndLocale('ALLERGENS', $this->currentLocale);
        if (null !== $attributeValue) {
            return $attributeValue->getValue();
        }

        return [];
    }

    public function getRestrictedDiets()
    {
        $attributeValue = $this->getAttributeByCodeAndLocale('RESTRICTED_DIETS', $this->currentLocale);
        if (null !== $attributeValue) {
            return $attributeValue->getValue();
        }

        return [];
    }

    public function hasNonAdditionalOptions()
    {
        foreach ($this->getOptions() as $option) {
            if (!$option->isAdditional()) {
                return true;
            }
        }

        return false;
    }
}
