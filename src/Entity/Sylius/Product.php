<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Comparable;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Sylius\Component\Product\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(),
        new Put(denormalizationContext: ['groups' => ['product_update']], security: 'is_granted(\'edit\', object)'),
        new Delete(security: 'is_granted(\'edit\', object)')
    ],
    normalizationContext: ['groups' => ['product']]
)]
#[ApiResource(
    uriTemplate: '/restaurants/{id}/products',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromClass: LocalBusiness::class, toProperty: 'restaurant')
    ],
    status: 200,
    normalizationContext: ['groups' => ['product']]
)]
class Product extends BaseProduct implements ProductInterface, Comparable, SoftDeleteableInterface
{
    use SoftDeleteable;

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
    public function isReusablePackagingEnabled(): bool
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
    public function getReusablePackagings(): Collection
    {
        return $this->reusablePackagings;
    }
    /**
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
    public function hasReusablePackagings(): bool
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
    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }
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
