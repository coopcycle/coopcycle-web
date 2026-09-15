<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Integration\Zelty\Dto\ZeltyTag;
use AppBundle\Integration\Zelty\ZeltyTaxonMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;
use Cocur\Slugify\SlugifyInterface;

/**
 * A tag still present in the catalog gets its stale product links cleaned
 * up by linkProductsToTaxon(), but nothing ever revisited a tag deleted
 * from Zelty entirely — the taxon it created would linger forever, and
 * disabling it wouldn't even hide it since Taxon.enabled isn't checked
 * anywhere in the menu display path. This locks in the cleanup: a tag
 * taxon missing from the current import is actually removed, while a
 * sibling menu taxon under the same root (an unrelated concept that
 * happens to share a parent) is left untouched.
 */
class ZeltyTaxonMapperTest extends TestCase
{
    public function testTagRemovedFromCatalogIsDeletedAlongWithItsProductLinks(): void
    {
        $root = new Taxon();
        $root->setCurrentLocale('fr');
        $root->setSlug('root');

        $staleTag = $this->childTaxon($root, 'ZT_GONE');
        $stalePt = new ProductTaxon();

        $removed = [];
        $em = $this->em(
            productTaxonsByTaxon: [$stalePt],
            onRemove: function ($entity) use (&$removed) {
                $removed[] = $entity;
            }
        );

        $mapper = new ZeltyTaxonMapper(
            $this->createMock(TaxonFactoryInterface::class),
            $em,
            $this->stubSlugify()
        );

        // No tags at all in this import: ZT_GONE no longer exists in Zelty.
        $mapper->importTags([], $root, [], 'fr');

        $this->assertContains($staleTag, $removed);
        $this->assertContains($stalePt, $removed);
    }

    public function testTagStillPresentIsNotRemoved(): void
    {
        $root = new Taxon();
        $root->setCurrentLocale('fr');
        $root->setSlug('root');

        $keptTag = $this->childTaxon($root, 'ZT_KEPT');

        $removed = [];
        $em = $this->em(
            productTaxonsByTaxon: [],
            onRemove: function ($entity) use (&$removed) {
                $removed[] = $entity;
            },
            taxonFoundByCode: $keptTag
        );

        $mapper = new ZeltyTaxonMapper(
            $this->createMock(TaxonFactoryInterface::class),
            $em,
            $this->stubSlugify()
        );

        $tag = new ZeltyTag(id: 'ZT_KEPT', name: 'Boissons');
        $mapper->importTags([$tag], $root, [], 'fr');

        $this->assertNotContains($keptTag, $removed);
    }

    public function testMenuDerivedSiblingTaxonIsNeverTouchedByTagCleanup(): void
    {
        $root = new Taxon();
        $root->setCurrentLocale('fr');
        $root->setSlug('root');

        // A menu taxon, sibling of tag taxons under the same root — not a
        // tag itself, so it must survive even though it's absent from $tags.
        $menuTaxon = $this->childTaxon($root, 'ZM12345');

        $removed = [];
        $em = $this->em(
            productTaxonsByTaxon: [],
            onRemove: function ($entity) use (&$removed) {
                $removed[] = $entity;
            }
        );

        $mapper = new ZeltyTaxonMapper(
            $this->createMock(TaxonFactoryInterface::class),
            $em,
            $this->stubSlugify()
        );

        $mapper->importTags([], $root, [], 'fr');

        $this->assertNotContains($menuTaxon, $removed);
    }

    private function childTaxon(Taxon $parent, string $zeltyId): Taxon
    {
        $child = new Taxon();
        $child->setCurrentLocale('fr');
        $child->setSlug($zeltyId);
        $child->setZeltyId($zeltyId);
        $child->setParent($parent);

        return $child;
    }

    private function stubSlugify(): SlugifyInterface
    {
        $slugify = $this->createMock(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('slug');

        return $slugify;
    }

    /**
     * @param array<int, object> $productTaxonsByTaxon rows returned for any ProductTaxon::findBy() call
     */
    private function em(
        array $productTaxonsByTaxon,
        callable $onRemove,
        ?Taxon $taxonFoundByCode = null
    ): EntityManagerInterface {
        $taxonRepository = $this->createMock(ObjectRepository::class);
        $taxonRepository->method('findOneBy')->willReturn($taxonFoundByCode);

        $productTaxonRepository = $this->createMock(ObjectRepository::class);
        $productTaxonRepository->method('findBy')->willReturn($productTaxonsByTaxon);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Taxon::class => $taxonRepository,
                ProductTaxon::class => $productTaxonRepository,
            }
        );
        $em->method('remove')->willReturnCallback($onRemove);
        $em->method('persist')->willReturnCallback(fn () => null);

        return $em;
    }
}
