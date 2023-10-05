<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Comparable;
use Sylius\Component\Product\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "put"={
 *       "method"="PUT",
 *       "denormalization_context"={"groups"={"product_update"}},
 *       "access_control"="is_granted('edit', object)"
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "access_control"="is_granted('edit', object)"
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"product"}}
 *   }
 * )
 */
class Product extends BaseProduct implements ProductInterface, Comparable
{
    protected $deletedAt;

    protected $reusablePackagingEnabled = false;

    protected $reusablePackagings;

    protected $images;

    protected $restaurant;

    protected $alcohol = false;

    public function __construct()
    {
        parent::__construct();

        $this->images = new ArrayCollection();
        $this->reusablePackagings = new ArrayCollection();
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

    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool
    {
        return $this->hasOption($optionValue->getOption());
    }

    /**
     * @return mixed
     */
    public function isReusablePackagingEnabled()
    {
        return $this->reusablePackagingEnabled;
    }

    /**
     * @param mixed $reusablePackagingEnabled
     *
     * @return self
     */
    public function setReusablePackagingEnabled($reusablePackagingEnabled)
    {
        $this->reusablePackagingEnabled = $reusablePackagingEnabled;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReusablePackagings()
    {
        return $this->reusablePackagings;
    }

    /**
     * @param ReusablePackagings $reusablePackagings
     *
     * @return self
     */
    public function addReusablePackaging(ReusablePackagings $reusablePackagings)
    {
        $reusablePackagings->setProduct($this);

        $this->reusablePackagings->add($reusablePackagings);

        return $this;
    }

    public function removeReusablePackaging(ReusablePackagings $reusablePackagings)
    {

    }

    public function clearReusablePackagings()
    {
        $this->reusablePackagings->clear();
    }

    public function hasReusablePackagings()
    {
        return count($this->reusablePackagings) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): Collection
    {
        $options = $this->options->toArray();

        uasort($options, function ($a, $b) {
            if ($a->getPosition() === $b->getPosition()) return 0;
            return $a->getPosition() < $b->getPosition() ? -1 : 1;
        });

        $values = array_map(
            function (ProductOptions $options) {
                return $options->getOption();
            },
            $options
        );

        return new ArrayCollection($values);
    }

    /**
     * {@inheritdoc}
     */
    public function addOption(ProductOptionInterface $option): void
    {
        if (!$this->hasOption($option)) {

            $productOptions = new ProductOptions();
            $productOptions->setProduct($this);
            $productOptions->setOption($option);

            $this->options->add($productOptions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeOption(ProductOptionInterface $option): void
    {
        if ($this->hasOption($option)) {
            foreach ($this->options as $productOptions) {
                if ($productOptions->getOption() === $option) {
                    $this->options->removeElement($productOptions);
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption(ProductOptionInterface $option): bool
    {
        return $this->getOptions()->contains($option);
    }

    public function getPositionForOption(ProductOptionInterface $option): int
    {
        if ($this->hasOption($option)) {
            foreach ($this->options as $productOptions) {
                if ($productOptions->getOption() === $option) {
                    return $productOptions->getPosition();
                }
            }
        }

        return -1;
    }

    public function addOptionAt(ProductOptionInterface $option, int $position): void
    {
        if (!$this->hasOption($option)) {
            $productOptions = new ProductOptions();
            $productOptions->setProduct($this);
            $productOptions->setOption($option);
            $productOptions->setPosition($position);

            $this->options->add($productOptions);
        } else {
            foreach ($this->options as $productOptions) {
                if ($productOptions->getOption() === $option) {
                    $productOptions->setPosition($position);
                    break;
                }
            }
        }
    }

    public function getProductOptions()
    {
        return $this->options;
    }

    public function getImages()
    {
        return $this->images;
    }

    public function addImage(ProductImage $image)
    {
        $image->setProduct($this);

        $this->images->add($image);
    }

    /**
     * Fix "Nesting level too deep - recursive dependency?"
     * @see https://github.com/Atlantic18/DoctrineExtensions/pull/2185
     */
    public function compareTo($other)
    {
        return $this === $other;
    }

    /**
     * @return LocalBusiness|null
     */
    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    /**
     * @param LocalBusiness|null $restaurant
     */
    public function setRestaurant(?LocalBusiness $restaurant)
    {
        $this->restaurant = $restaurant;
    }

    public function isAlcohol(): bool
    {
        return $this->alcohol;
    }

    public function setAlcohol(bool $alcohol)
    {
        $this->alcohol = $alcohol;
    }
}
