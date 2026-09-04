<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use AppBundle\Integration\Zelty\Dto\ZeltyMenuPart;
use AppBundle\Integration\Zelty\Dto\ZeltyPrice;
use AppBundle\Integration\Zelty\ZeltyMenuMapper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

/**
 * Zelty never sends tax_rules on a menu item, only on its component dishes
 * (confirmed against the live Naofood catalog: 30/30 menus have no tax_rules
 * key at all). Flattening every menu to the restaurant's default (lowest)
 * tax category under-collects tax whenever a menu bundles a higher-rated
 * component — e.g. a 10% burger + fries with a 5.5% drink, sold as one menu.
 *
 * This locks in the stopgap: tax the whole menu at its highest-rated
 * component's category instead of always falling back to the default.
 */
class ZeltyMenuMapperTest extends TestCase
{
    public function testMenuIsTaxedAtItsHighestRatedComponent(): void
    {
        $reduced = $this->taxCategory('BASE_REDUCED');
        $intermediary = $this->taxCategory('BASE_INTERMEDIARY');
        $standard = $this->taxCategory('BASE_STANDARD');

        // Ascending by rate, as ZeltyTaxesMapper::getOrderedTaxCategories() returns it.
        $orderedTaxCategories = [$reduced, $intermediary, $standard];

        $burger = $this->dishProduct($intermediary);
        $fries = $this->dishProduct($intermediary);
        $drink = $this->dishProduct($reduced);

        $productsMap = [
            'ZD_BURGER' => $burger,
            'ZD_FRIES' => $fries,
            'ZD_DRINK' => $drink,
        ];

        $menuPartsMap = [
            'ZMP_BURGER' => new ZeltyMenuPart(id: 'ZMP_BURGER', name: 'Burger', dishIds: ['ZD_BURGER']),
            'ZMP_SIDE' => new ZeltyMenuPart(id: 'ZMP_SIDE', name: 'Side', dishIds: ['ZD_FRIES']),
            'ZMP_DRINK' => new ZeltyMenuPart(id: 'ZMP_DRINK', name: 'Drink', dishIds: ['ZD_DRINK']),
        ];

        $menu = new ZeltyItem(
            id: 'ZM1',
            type: ZeltyItem::TYPE_MENU,
            name: 'Menu Enfant',
            price: new ZeltyPrice(price: 690),
            parts: ['ZMP_BURGER', 'ZMP_SIDE', 'ZMP_DRINK'],
        );

        $mapper = $this->buildMapper();
        $restaurant = new LocalBusiness();

        $menuMap = $mapper->importMenus(
            [$menu],
            $menuPartsMap,
            $productsMap,
            [],
            $restaurant,
            'fr',
            [], // no menu-level tax_rules, per the live catalog
            $reduced, // default tax category, as ZeltyTaxesMapper::getDefaultTaxCategory() would give
            $orderedTaxCategories
        );

        $menuProduct = $menuMap['ZM1'];
        $variant = $menuProduct->getVariants()->first();

        // Not the default (5.5%): the menu includes a 10% component, so the
        // whole bundle is taxed at 10%, not silently under-collected at 5.5%.
        $this->assertSame($intermediary, $variant->getTaxCategory());
    }

    public function testMenuWithOnlyReducedRateComponentsKeepsReducedRate(): void
    {
        $reduced = $this->taxCategory('BASE_REDUCED');
        $intermediary = $this->taxCategory('BASE_INTERMEDIARY');
        $orderedTaxCategories = [$reduced, $intermediary];

        $drinkA = $this->dishProduct($reduced);
        $drinkB = $this->dishProduct($reduced);

        $productsMap = [
            'ZD_A' => $drinkA,
            'ZD_B' => $drinkB,
        ];

        $menuPartsMap = [
            'ZMP_A' => new ZeltyMenuPart(id: 'ZMP_A', name: 'A', dishIds: ['ZD_A']),
            'ZMP_B' => new ZeltyMenuPart(id: 'ZMP_B', name: 'B', dishIds: ['ZD_B']),
        ];

        $menu = new ZeltyItem(
            id: 'ZM2',
            type: ZeltyItem::TYPE_MENU,
            name: 'Menu Boissons',
            price: new ZeltyPrice(price: 500),
            parts: ['ZMP_A', 'ZMP_B'],
        );

        $mapper = $this->buildMapper();
        $restaurant = new LocalBusiness();

        $menuMap = $mapper->importMenus(
            [$menu],
            $menuPartsMap,
            $productsMap,
            [],
            $restaurant,
            'fr',
            [],
            $reduced,
            $orderedTaxCategories
        );

        $variant = $menuMap['ZM2']->getVariants()->first();

        $this->assertSame($reduced, $variant->getTaxCategory());
    }

    /**
     * Reproduces the production report: after a catalog re-import, the menu
     * product's tax category looked correct in the admin, but orders placed
     * against it kept the old (reduced) rate.
     *
     * Nothing in this codebase stops an admin from adding a second variant
     * to a Zelty-managed product by hand through the product edit form
     * (ProductType has its own "add variant" flow). When that happens,
     * Collection::first() on the variants no longer reliably returns the one
     * ZeltyMenuMapper itself manages — it can land on the unrelated
     * hand-added variant instead, leaving the real one (the one actual
     * orders reference) never updated.
     */
    public function testExistingMenuWithAnExtraHandAddedVariantUpdatesTheRightOne(): void
    {
        $reduced = $this->taxCategory('BASE_REDUCED');
        $intermediary = $this->taxCategory('BASE_INTERMEDIARY');
        $orderedTaxCategories = [$reduced, $intermediary];

        $burger = $this->dishProduct($intermediary);
        $productsMap = ['ZD_BURGER' => $burger];
        $menuPartsMap = [
            'ZMP_BURGER' => new ZeltyMenuPart(id: 'ZMP_BURGER', name: 'Burger', dishIds: ['ZD_BURGER']),
        ];

        $existingProduct = new Product();
        $existingProduct->setFallbackLocale('fr');
        $existingProduct->setCurrentLocale('fr');

        // A variant an admin added by hand, unrelated to Zelty's own
        // "{menuId}_variant" naming, added first so it sorts before the real
        // one in the collection.
        $handAddedVariant = new ProductVariant();
        $handAddedVariant->setCode('admin-added-variant');
        $handAddedVariant->setFallbackLocale('fr');
        $handAddedVariant->setCurrentLocale('fr');
        $handAddedVariant->setTaxCategory($reduced);
        $existingProduct->addVariant($handAddedVariant);

        // The variant ZeltyMenuMapper itself created and manages, still on
        // the stale rate from before the catalog fix.
        $zeltyVariant = new ProductVariant();
        $zeltyVariant->setCode('ZM3_variant');
        $zeltyVariant->setFallbackLocale('fr');
        $zeltyVariant->setCurrentLocale('fr');
        $zeltyVariant->setTaxCategory($reduced);
        $existingProduct->addVariant($zeltyVariant);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => ($criteria['code'] ?? null) === 'ZM3' ? $existingProduct : null
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getFilters')->willReturn($filters);

        $productFactory = $this->createMock(ProductFactoryInterface::class);
        $variantFactory = $this->createMock(ProductVariantFactoryInterface::class);
        $slugify = $this->createMock(SlugifyInterface::class);

        $mapper = new ZeltyMenuMapper($productFactory, $variantFactory, $em, $slugify);

        $menu = new ZeltyItem(
            id: 'ZM3',
            type: ZeltyItem::TYPE_MENU,
            name: 'Menu Burger',
            price: new ZeltyPrice(price: 690),
            parts: ['ZMP_BURGER'],
        );

        $restaurant = new LocalBusiness();

        $menuMap = $mapper->importMenus(
            [$menu],
            $menuPartsMap,
            $productsMap,
            [],
            $restaurant,
            'fr',
            [],
            $reduced,
            $orderedTaxCategories
        );

        // The hand-added variant is untouched...
        $this->assertSame($reduced, $handAddedVariant->getTaxCategory());
        // ...while the real Zelty-managed variant — the one orders reference — is fixed.
        $this->assertSame($intermediary, $zeltyVariant->getTaxCategory());
        $this->assertSame($zeltyVariant, $menuMap['ZM3']->getVariants()->last());
    }

    private function dishProduct(TaxCategory $taxCategory): Product
    {
        $product = new Product();
        $product->setFallbackLocale('fr');
        $product->setCurrentLocale('fr');

        $variant = new ProductVariant();
        $variant->setTaxCategory($taxCategory);
        $product->addVariant($variant);

        return $product;
    }

    private function taxCategory(string $code): TaxCategory
    {
        $category = new TaxCategory();
        $category->setCode($code);

        return $category;
    }

    private function buildMapper(): ZeltyMenuMapper
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getFilters')->willReturn($filters);

        $productFactory = $this->createMock(ProductFactoryInterface::class);
        $productFactory->method('createNew')->willReturnCallback(function () {
            // The real Sylius factory sets this too; ZeltyMenuMapper::createMenuProduct()
            // calls setSlug() (which needs a locale) before setCurrentLocale().
            $product = new Product();
            $product->setFallbackLocale('fr');
            $product->setCurrentLocale('fr');

            return $product;
        });

        $variantFactory = $this->createMock(ProductVariantFactoryInterface::class);
        $variantFactory->method('createForProduct')->willReturnCallback(function () {
            $variant = new ProductVariant();
            $variant->setFallbackLocale('fr');
            $variant->setCurrentLocale('fr');

            return $variant;
        });

        $slugify = $this->createMock(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('menu-slug');

        return new ZeltyMenuMapper($productFactory, $variantFactory, $em, $slugify);
    }
}
