<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use AppBundle\Integration\Zelty\ZeltyImportService;
use AppBundle\Integration\Zelty\ZeltyMenuMapper;
use AppBundle\Integration\Zelty\ZeltyOptionMapper;
use AppBundle\Integration\Zelty\ZeltyProductMapper;
use AppBundle\Integration\Zelty\ZeltyTaxesMapper;
use AppBundle\Integration\Zelty\ZeltyTaxonMapper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;

/**
 * A restaurant's Zelty account can hold several catalogs, and the root taxon
 * an import writes to used to be keyed by restaurant ID alone
 * (see ZeltyImportService::createOrGetRootTaxon()) — so every catalog of a
 * restaurant landed in the same tree. Naofood's restaurant 178 had two
 * unrelated catalogs silently mixed that way; guarding against the mix-up
 * then dead-ended restaurant 82, which had pulled an empty catalog by
 * mistake and could no longer import the right one.
 *
 * This locks in the way out: the root taxon is keyed by restaurant *and*
 * catalog, so catalogs live side by side as separate menus, and a
 * restaurant imported before that keeps using its existing taxon for the
 * catalog that built it.
 */
class ZeltyImportServiceTest extends TestCase
{
    public function testFirstImportCreatesATaxonKeyedByRestaurantAndCatalog(): void
    {
        $restaurant = $this->restaurant();
        $catalog = $this->catalog('naofood-catalog-id', 'Naofood');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $em = $this->em($repository);
        $em->expects($this->atLeastOnce())->method('persist');

        $service = $this->buildService($em);
        $service->import($catalog, $restaurant);

        $this->assertTrue($restaurant->getTaxons()->count() > 0);
        $taxon = $restaurant->getTaxons()->first();
        $this->assertSame('naofood-catalog-id', $taxon->getZeltyId());
        $this->assertSame(
            sprintf('zelty_import_%d_naofood-catalog-id', $restaurant->getId()),
            $taxon->getCode()
        );
    }

    public function testReimportingTheSameCatalogReusesItsTaxon(): void
    {
        $restaurant = $this->restaurant();
        $catalog = $this->catalog('naofood-catalog-id', 'Naofood');

        $existingTaxon = new Taxon();
        $existingTaxon->setCode(sprintf('zelty_import_%d_naofood-catalog-id', $restaurant->getId()));
        $existingTaxon->setCurrentLocale('fr');
        $existingTaxon->setZeltyId('naofood-catalog-id');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $criteria['code'] === $existingTaxon->getCode() ? $existingTaxon : null
        );

        $em = $this->em($repository);

        $service = $this->buildService($em);
        $service->import($catalog, $restaurant);

        $this->assertSame([$existingTaxon], $restaurant->getTaxons()->toArray());
    }

    public function testALegacyTaxonWithoutACatalogIdAdoptsTheCurrentCatalog(): void
    {
        $restaurant = $this->restaurant();
        $catalog = $this->catalog('naofood-catalog-id', 'Naofood');

        // A taxon created before catalog ids were recorded at all.
        $legacyTaxon = new Taxon();
        $legacyTaxon->setCode('zelty_import_' . $restaurant->getId());
        $legacyTaxon->setCurrentLocale('fr');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $criteria['code'] === $legacyTaxon->getCode() ? $legacyTaxon : null
        );

        $em = $this->em($repository);

        $service = $this->buildService($em);
        $service->import($catalog, $restaurant);

        $this->assertSame('naofood-catalog-id', $legacyTaxon->getZeltyId());
        $this->assertSame([$legacyTaxon], $restaurant->getTaxons()->toArray());
    }

    public function testALegacyTaxonBuiltFromAnotherCatalogIsLeftAloneInsteadOfBlocking(): void
    {
        $restaurant = $this->restaurant();

        // Restaurant 82: an empty catalog was pulled by mistake, and the
        // right one must still be importable afterwards.
        $legacyTaxon = new Taxon();
        $legacyTaxon->setCode('zelty_import_' . $restaurant->getId());
        $legacyTaxon->setCurrentLocale('fr');
        $legacyTaxon->setZeltyId('commande-en-ligne-catalog-id');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $criteria['code'] === $legacyTaxon->getCode() ? $legacyTaxon : null
        );

        $em = $this->em($repository);

        $service = $this->buildService($em);
        $service->import($this->catalog('click-and-collect-catalog-id', 'Click&Collect'), $restaurant);

        $this->assertSame('commande-en-ligne-catalog-id', $legacyTaxon->getZeltyId());

        $taxons = $restaurant->getTaxons();
        $this->assertCount(1, $taxons);

        $imported = $taxons->first();
        $this->assertNotSame($legacyTaxon, $imported);
        $this->assertSame('click-and-collect-catalog-id', $imported->getZeltyId());
        $this->assertSame(
            sprintf('zelty_import_%d_click-and-collect-catalog-id', $restaurant->getId()),
            $imported->getCode()
        );
    }

    public function testEachCatalogOfARestaurantGetsItsOwnTaxon(): void
    {
        $restaurant = $this->restaurant();

        $taxonsByCode = [];

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $taxonsByCode[$criteria['code']] ?? null
        );

        $em = $this->em($repository);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$taxonsByCode) {
            if ($entity instanceof Taxon) {
                $taxonsByCode[$entity->getCode()] = $entity;
            }
        });

        $service = $this->buildService($em);
        $service->import($this->catalog('first-catalog-id', 'Click&Collect'), $restaurant);
        $service->import($this->catalog('second-catalog-id', 'Click&Collect - Copie pour LCR'), $restaurant);

        $this->assertCount(2, $restaurant->getTaxons());

        $zeltyIds = array_map(
            fn (Taxon $taxon) => $taxon->getZeltyId(),
            $restaurant->getTaxons()->toArray()
        );
        $this->assertSame(['first-catalog-id', 'second-catalog-id'], $zeltyIds);
    }

    private function catalog(string $id, string $name): ZeltyCatalog
    {
        return new ZeltyCatalog(
            id: $id,
            name: $name,
            locale: 'fr_FR',
            currency: 'EUR',
        );
    }

    private function restaurant(): LocalBusiness
    {
        $restaurant = new LocalBusiness();
        $restaurant->setZeltyApiKey('some-api-key');

        return $restaurant;
    }

    private function em(ObjectRepository $taxonRepository): EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($taxonRepository);

        return $em;
    }

    private function buildService(EntityManagerInterface $em): ZeltyImportService
    {
        $optionMapper = $this->createMock(ZeltyOptionMapper::class);
        $optionMapper->method('importOptions')->willReturn([]);

        $productMapper = $this->createMock(ZeltyProductMapper::class);
        $productMapper->method('importDishes')->willReturn([]);

        $menuMapper = $this->createMock(ZeltyMenuMapper::class);
        $menuMapper->method('importMenus')->willReturn([]);

        $taxonMapper = $this->createMock(ZeltyTaxonMapper::class);

        $taxesMapper = $this->createMock(ZeltyTaxesMapper::class);
        $taxesMapper->method('importTaxes')->willReturn([]);
        $taxesMapper->method('getDefaultTaxCategory')->willReturn(null);
        $taxesMapper->method('getOrderedTaxCategories')->willReturn([]);

        $slugify = $this->createMock(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('imported-catalog-from-zelty');

        $localeProvider = $this->createMock(LocaleProviderInterface::class);
        $localeProvider->method('getDefaultLocaleCode')->willReturn('fr');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findZeltyProductsForRestaurantNotIn')->willReturn([]);

        return new ZeltyImportService(
            $optionMapper,
            $productMapper,
            $menuMapper,
            $taxonMapper,
            $taxesMapper,
            $slugify,
            $localeProvider,
            $em,
            $productRepository,
        );
    }
}
