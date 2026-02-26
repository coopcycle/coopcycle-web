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
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;

class ZeltyTaxonMapper
{
    public function __construct(
        private TaxonFactoryInterface $taxonFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    public function importMenus(
        array $menus,
        Taxon $parentTaxon,
        array $productsMap,
        array $menuPartsMap,
        string $locale
    ): array {
        $taxonMap = [];

        foreach ($menus as $menu) {
            $taxon = $this->importMenu($menu, $parentTaxon, $productsMap, $menuPartsMap, $locale);
            $taxonMap[$taxon->getCode()] = $taxon;
        }

        return $taxonMap;
    }

    public function importTags(
        array $tags,
        Taxon $parentTaxon,
        array $productsMap,
        array $menusMap,
        string $locale
    ): void {
        foreach ($tags as $tag) {
            $this->importTag($tag, $parentTaxon, $productsMap, $menusMap, $locale);
        }
    }

    private function importMenu(
        ZeltyItem $menu,
        Taxon $parentTaxon,
        array $productsMap,
        array $menuPartsMap,
        string $locale
    ): Taxon {
        $taxon = $this->em->getRepository(Taxon::class)->findOneBy([
            'code' => $menu->id,
        ]);

        if (null === $taxon) {
            /** @var Taxon $taxon */
            $taxon = $this->taxonFactory->createNew();
            $taxon->setCode($menu->id);
            $taxon->setSlug($this->slugify->slugify(($menu->name ?? $menu->id) . '-' . $menu->id));

            $taxon->setCurrentLocale($locale);

            if ($menu->name) {
                $taxon->setName($menu->name);
            }

            if ($menu->description) {
                $taxon->setDescription($menu->description);
            }

            $taxon->setEnabled(!$menu->disabled);
            $taxon->setParent($parentTaxon);

            $this->em->persist($taxon);
            $this->em->flush();
        }

        $this->importMenuParts($menu, $taxon, $productsMap, $menuPartsMap, $locale);

        return $taxon;
    }

    private function importMenuParts(
        ZeltyItem $menu,
        Taxon $parentTaxon,
        array $productsMap,
        array $menuPartsMap,
        string $locale
    ): void {
        $existingChildren = [];
        foreach ($parentTaxon->getChildren() as $child) {
            $existingChildren[$child->getCode()] = $child;
        }

        $handledPartIds = [];

        foreach ($menu->parts as $partId) {
            $handledPartIds[] = $partId;

            if (!isset($menuPartsMap[$partId])) {
                continue;
            }

            $part = $menuPartsMap[$partId];
            $sectionCode = $menu->id . '_' . $partId;

            $section = $existingChildren[$sectionCode] ?? null;

            if (null === $section) {
                /** @var Taxon $section */
                $section = $this->taxonFactory->createNew();
                $section->setCode($sectionCode);
                $section->setSlug($this->slugify->slugify(($part->name ?? $partId) . '-' . $partId));

                $section->setCurrentLocale($locale);

                if ($part->name) {
                    $section->setName($part->name);
                }

                $section->setEnabled(!$menu->disabled);
                $section->setParent($parentTaxon);

                $this->em->persist($section);
                $this->em->flush();
            }

            $this->linkProductsToSection($section, $part->dishIds, $productsMap);
        }

        foreach ($existingChildren as $code => $child) {
            $partId = str_replace($menu->id . '_', '', $code);
            if (!in_array($partId, $handledPartIds)) {
                $this->em->remove($child);
            }
        }

        $this->em->flush();
    }

    private function linkProductsToSection(Taxon $section, array $dishIds, array $productsMap): void
    {
        $this->em->clear(ProductTaxon::class);
        $existingProductTaxons = $this->em->getRepository(ProductTaxon::class)->findBy([
            'taxon' => $section,
        ]);
        $existingMap = [];
        foreach ($existingProductTaxons as $pt) {
            $existingMap[$pt->getProduct()->getCode()] = $pt;
        }

        foreach ($dishIds as $position => $dishId) {
            if (!isset($productsMap[$dishId])) {
                continue;
            }

            /** @var Product $product */
            $product = $productsMap[$dishId];

            if (!isset($existingMap[$dishId])) {
                if (!$section->containsProduct($product)) {
                    $productTaxon = new ProductTaxon();
                    $productTaxon->setTaxon($section);
                    $productTaxon->setProduct($product);
                    $productTaxon->setPosition($position);
                    $this->em->persist($productTaxon);
                }
            } else {
                $productTaxon = $existingMap[$dishId];
                $productTaxon->setPosition($position);
                unset($existingMap[$dishId]);
            }
        }

        foreach ($existingMap as $productTaxon) {
            $this->em->remove($productTaxon);
        }

        $this->em->flush();
    }

    private function importTag(
        ZeltyTag $tag,
        Taxon $parentTaxon,
        array $productsMap,
        array $menusMap,
        string $locale
    ): Taxon {
        $taxon = $this->em->getRepository(Taxon::class)->findOneBy([
            'code' => $tag->id,
        ]);

        if (null === $taxon) {
            /** @var Taxon $taxon */
            $taxon = $this->taxonFactory->createNew();
            $taxon->setCode($tag->id);
            $taxon->setSlug($this->slugify->slugify(($tag->name ?? $tag->id) . '-' . $tag->id));

            $taxon->setCurrentLocale($locale);

            if ($tag->name) {
                $taxon->setName($tag->name);
            }

            if ($tag->description) {
                $taxon->setDescription($tag->description);
            }

            $taxon->setEnabled(!$tag->disabled);
            $taxon->setParent($parentTaxon);

            $this->em->persist($taxon);
            $this->em->flush();
        }

        $this->linkItemsToTag($taxon, $tag->itemIds, $productsMap, $menusMap);

        return $taxon;
    }

    private function linkItemsToTag(Taxon $tag, array $itemIds, array $productsMap, array $menusMap): void
    {
        $this->em->clear(ProductTaxon::class);
        $existingProductTaxons = $this->em->getRepository(ProductTaxon::class)->findBy([
            'taxon' => $tag,
        ]);
        $existingMap = [];
        foreach ($existingProductTaxons as $pt) {
            $existingMap[$pt->getProduct()->getCode()] = $pt;
        }

        foreach ($itemIds as $position => $itemId) {
            if (!isset($productsMap[$itemId])) {
                continue;
            }

            $product = $productsMap[$itemId];

            if (!isset($existingMap[$itemId])) {
                if (!$tag->containsProduct($product)) {
                    $productTaxon = new ProductTaxon();
                    $productTaxon->setTaxon($tag);
                    $productTaxon->setProduct($product);
                    $productTaxon->setPosition($position);
                    $this->em->persist($productTaxon);
                }
            } else {
                $productTaxon = $existingMap[$itemId];
                $productTaxon->setPosition($position);
                unset($existingMap[$itemId]);
            }
        }

        foreach ($existingMap as $productTaxon) {
            $this->em->remove($productTaxon);
        }

        $this->em->flush();
    }
}
