<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use AppBundle\Integration\Zelty\Dto\ZeltyMenuPart;
use AppBundle\Integration\Zelty\Dto\ZeltyTag;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;

class ZeltyTaxonMapper
{
    public function __construct(
        private TaxonFactoryInterface $taxonFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

    public function importMenus(
        array $menus,
        Taxon $parentTaxon,
        array $productsMap,
        array $menuPartsMap,
        string $locale,
        LocalBusiness $restaurant
    ): array {
        $taxonMap = [];
        foreach ($menus as $menu) {
            $taxon = $this->importMenuAsTaxon($menu, $parentTaxon, $locale);
            $this->importMenuParts($menu, $taxon, $productsMap, $menuPartsMap, $locale, $restaurant);
            $taxonMap[$taxon->getCode()] = $taxon;
        }
        $this->em->flush();
        return $taxonMap;
    }

    public function importTags(
        array $tags,
        Taxon $parentTaxon,
        array $productsMap,
        string $locale
    ): void {
        foreach ($tags as $tag) {
            $taxon = $this->importTagAsTaxon($tag, $parentTaxon, $locale);
            $this->linkProductsToTaxon($taxon, $tag->itemIds, $productsMap);
        }
        $this->em->flush();
    }

    private function importMenuAsTaxon(ZeltyItem $menu, Taxon $parentTaxon, string $locale): Taxon
    {
        $taxon = $this->findOrCreateTaxon($menu->id, $locale);
        $this->updateTaxonFromItem($taxon, $menu, $parentTaxon);
        return $taxon;
    }

    private function importTagAsTaxon(ZeltyTag $tag, Taxon $parentTaxon, string $locale): Taxon
    {
        $taxon = $this->findOrCreateTaxon($tag->id, $locale);
        $this->updateTaxonFromTag($taxon, $tag, $parentTaxon);
        return $taxon;
    }

    private function findOrCreateTaxon(string $code, string $locale): Taxon
    {
        $taxon = $this->em->getRepository(Taxon::class)->findOneBy(['code' => $code]);
        if ($taxon !== null) {
            $taxon->setCurrentLocale($locale);
            return $taxon;
        }

        /** @var Taxon $taxon */
        $taxon = $this->taxonFactory->createNew();
        $taxon->setCode(Uuid::uuid4()->toString());
        $taxon->setCurrentLocale($locale);
        $this->em->persist($taxon);

        return $taxon;
    }

    private function updateTaxonFromItem(Taxon $taxon, ZeltyItem $item, Taxon $parentTaxon): void
    {
        $taxon->setName($item->name);
        $taxon->setDescription($item->description);
        $taxon->setEnabled(!$item->disabled);
        $taxon->setSlug($this->generateSlug($item->name, $item->id));
        $taxon->setParent($parentTaxon);
        $taxon->setZeltyCode($item->id);
    }

    private function updateTaxonFromTag(Taxon $taxon, ZeltyTag $tag, Taxon $parentTaxon): void
    {
        $taxon->setName($tag->name);
        $taxon->setDescription($tag->description);
        $taxon->setEnabled(!$tag->disabled);
        $taxon->setSlug($this->generateSlug($tag->name, $tag->id));
        $taxon->setParent($parentTaxon);
        $taxon->setZeltyCode($tag->id);
    }

    private function generateSlug(?string $name, string $id): string
    {
        return $this->slugify->slugify(($name ?? $id) . '-' . $id);
    }

    private function importMenuParts(
        ZeltyItem $menu,
        Taxon $menuTaxon,
        array $productsMap,
        array $menuPartsMap,
        string $locale,
        LocalBusiness $restaurant
    ): void {
        $this->ensureRestaurantHasTaxon($restaurant, $menuTaxon);
        $childrenIndex = $this->indexChildrenByCode($menuTaxon);

        foreach ($menu->parts as $partId) {
            if (!isset($menuPartsMap[$partId])) {
                continue;
            }

            $part = $menuPartsMap[$partId];
            $sectionTaxon = $this->findOrCreateSectionTaxon($part, $partId, $menu, $childrenIndex, $menuTaxon, $locale, $restaurant);
            $this->linkProductsToTaxon($sectionTaxon, $part->dishIds, $productsMap);
        }
    }

    private function findOrCreateSectionTaxon(
        ZeltyMenuPart $part,
        string $partId,
        ZeltyItem $menu,
        array $existingChildren,
        Taxon $parent,
        string $locale,
        LocalBusiness $restaurant
    ): Taxon {
        if (isset($existingChildren[$partId])) {
            return $this->updateSectionTaxon($existingChildren[$partId], $part, $partId, $menu, $locale, $restaurant);
        }

        return $this->createSectionTaxon($part, $partId, $menu, $parent, $locale, $restaurant);
    }

    private function createSectionTaxon(
        ZeltyMenuPart $part,
        string $partId,
        ZeltyItem $menu,
        Taxon $parent,
        string $locale,
        LocalBusiness $restaurant
    ): Taxon {
        /** @var Taxon $section */
        $section = $this->taxonFactory->createNew();
        $section->setCode(Uuid::uuid4()->toString());
        $section->setZeltyCode($partId);
        $section->setCurrentLocale($locale);
        $section->setName(sprintf('%s - %s', $menu->name, $part->name));
        $section->setEnabled(!$menu->disabled);
        $section->setSlug($this->generateSlug($part->name, $partId));
        $section->setParent($parent);

        $this->em->persist($section);
        $this->ensureRestaurantHasTaxon($restaurant, $section);

        return $section;
    }

    private function updateSectionTaxon(
        Taxon $section,
        ZeltyMenuPart $part,
        string $partId,
        ZeltyItem $menu,
        string $locale,
        LocalBusiness $restaurant
    ): Taxon {
        $section->setCurrentLocale($locale);
        $section->setName(sprintf('%s - %s', $menu->name, $part->name));
        $section->setEnabled(!$menu->disabled);
        $section->setSlug($this->generateSlug($part->name, $partId));
        $this->ensureRestaurantHasTaxon($restaurant, $section);

        return $section;
    }

    private function ensureRestaurantHasTaxon(LocalBusiness $restaurant, Taxon $taxon): void
    {
        if (!$restaurant->getTaxons()->contains($taxon)) {
            $restaurant->addTaxon($taxon);
        }
    }

    private function indexChildrenByCode(Taxon $taxon): array
    {
        $index = [];
        foreach ($taxon->getChildren() as $child) {
            $index[$child->getCode()] = $child;
        }
        return $index;
    }

    private function linkProductsToTaxon(Taxon $taxon, array $productIds, array $productsMap): void
    {
        $existing = $this->em->getRepository(ProductTaxon::class)->findBy(['taxon' => $taxon]);
        $existingByCode = [];
        foreach ($existing as $pt) {
            $existingByCode[$pt->getProduct()->getCode()] = $pt;
        }

        foreach ($productIds as $position => $productId) {
            if (!isset($productsMap[$productId])) {
                continue;
            }

            $product = $productsMap[$productId];

            if (isset($existingByCode[$productId])) {
                $existingByCode[$productId]->setPosition($position);
                unset($existingByCode[$productId]);
            } elseif (!$taxon->containsProduct($product)) {
                $this->createProductTaxon($taxon, $product, $position);
            }
        }

        foreach ($existingByCode as $pt) {
            $this->em->remove($pt);
        }
    }

    private function createProductTaxon(Taxon $taxon, Product $product, int $position): void
    {
        $productTaxon = new ProductTaxon();
        $productTaxon->setTaxon($taxon);
        $productTaxon->setProduct($product);
        $productTaxon->setPosition($position);
        $this->em->persist($productTaxon);
    }
}
