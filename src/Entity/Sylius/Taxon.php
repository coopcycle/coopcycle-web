<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Api\Dto\MenuInput;
use AppBundle\Api\State\RestaurantMenuProcessor;
use AppBundle\Api\State\RestaurantMenuProvider;
use AppBundle\Api\State\RestaurantMenuSectionProcessor;
use AppBundle\Api\State\RestaurantMenuSectionProvider;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Comparable;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\Taxon as BaseTaxon;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'Menu',
    types: ['http://schema.org/Menu'],
    operations: [
        new Get(
            uriTemplate: '/restaurants/menus/{id}',
            provider: RestaurantMenuProvider::class,
            normalizationContext: ['groups' => ['restaurant_menu']],
        ),
        new Delete(
            uriTemplate: '/restaurants/menus/{id}',
            security: 'is_granted("edit", object)'
        ),
        new Put(
            uriTemplate: '/restaurants/menus/{id}',
            processor: RestaurantMenuProcessor::class,
            input: MenuInput::class,
            normalizationContext: ['groups' => ['restaurant_menus']],
            denormalizationContext: ['groups' => ['restaurant_menus']],
            security: 'is_granted("edit", object)'
        ),
        new Post(
            uriTemplate: '/restaurants/menus/{id}/sections',
            processor: RestaurantMenuSectionProcessor::class,
            input: MenuInput::class,
            normalizationContext: ['groups' => ['restaurant_menu']],
            denormalizationContext: ['groups' => ['restaurant_menu']],
            security: 'is_granted("edit", object)'
        ),
        new Put(
            uriTemplate: '/restaurants/menus/{id}/sections/{sectionId}',
            provider: RestaurantMenuSectionProvider::class,
            processor: RestaurantMenuSectionProcessor::class,
            input: MenuInput::class,
            normalizationContext: ['groups' => ['restaurant_menu']],
            denormalizationContext: ['groups' => ['restaurant_menu']],
            security: 'is_granted("edit", object)'
        ),
        new Get(
            uriTemplate: '/restaurants/menus/{id}/sections/{sectionId}',
            provider: RestaurantMenuSectionProvider::class,
            normalizationContext: ['groups' => ['restaurant_menu']],
            denormalizationContext: ['groups' => ['restaurant_menu']],
        ),
        new Delete(
            uriTemplate: '/restaurants/menus/{id}/sections/{sectionId}',
            provider: RestaurantMenuSectionProvider::class,
            normalizationContext: ['groups' => ['restaurant_menu']],
            denormalizationContext: ['groups' => ['restaurant_menu']],
            security: 'is_granted("edit", object)'
        ),
    ],
    normalizationContext: ['groups' => ['restaurant_menu']]
)]
class Taxon extends BaseTaxon implements Comparable
{
    private $taxonProducts;

    public function __construct()
    {
        parent::__construct();

        $this->taxonProducts = new ArrayCollection();
    }

    public function getTaxonProducts()
    {
        return $this->taxonProducts;
    }

    public function setTaxonProducts(Collection $taxonProducts)
    {
        $this->taxonProducts = $taxonProducts;
    }

    public function addProduct(ProductInterface $product, int $position = 0)
    {
        if (!$this->containsProduct($product)) {
            $productTaxon = new ProductTaxon();
            $productTaxon->setTaxon($this);
            $productTaxon->setProduct($product);
            $productTaxon->setPosition($position);

            $this->taxonProducts->add($productTaxon);
        } else {
            foreach ($this->taxonProducts as $taxonProduct) {
                if ($taxonProduct->getProduct() === $product) {
                    $taxonProduct->setPosition($position);
                    // var_dump($position);
                    break;
                }
            }
        }
    }

    public function getProducts()
    {
        return $this->taxonProducts->map(function (ProductTaxon $productTaxon): ProductInterface {
            return $productTaxon->getProduct();
        });
    }

    public function removeProduct(ProductInterface $product)
    {
        foreach ($this->taxonProducts as $taxonProduct) {
            if ($taxonProduct->getProduct() === $product) {
                $this->taxonProducts->removeElement($taxonProduct);
                break;
            }
        }
    }

    public function containsProduct(ProductInterface $product): bool
    {
        foreach ($this->taxonProducts as $taxonProduct) {
            if ($taxonProduct->getProduct() === $product) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return count($this->taxonProducts) === 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see https://github.com/Sylius/Sylius/issues/10797
     * @see https://github.com/Sylius/Sylius/pull/11329
     * @see https://github.com/Atlantic18/DoctrineExtensions/pull/2185
     */
    public function compareTo($other)
    {
        return $this->code === $other->getCode();
    }

    #[Groups(['restaurant_menu', 'restaurant_menus', 'restaurant_menu_without_options'])]
    public function getMenuChildren()
    {
        return $this->isRoot() ? $this->getChildren() : $this->getProducts();
    }

    #[Groups(['restaurant_menu', 'restaurant_menus'])]
    public function getIdentifier()
    {
        return $this->getCode();
    }
}
