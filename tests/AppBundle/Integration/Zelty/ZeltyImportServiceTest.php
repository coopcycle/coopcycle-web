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
 * The root taxon a Zelty import writes to is keyed only by restaurant ID
 * (see ZeltyImportService::createOrGetRootTaxon()), not by which Zelty
 * catalog produced it. Naofood's restaurant 178 had a different catalog
 * (Click and Collect/Delicity/Sunday) imported into it by mistake at some
 * point, then the correct "Naofood" catalog imported again later — with
 * nothing in the code noticing the switch, both silently shared and
 * rewrote the same taxon tree.
 *
 * This locks in the guard: a restaurant's root taxon remembers which
 * catalog built it, and importing a *different* catalog for the same
 * restaurant is refused instead of silently mixing the two together.
 */
class ZeltyImportServiceTest extends TestCase
{
    public function testFirstImportStampsTheRootTaxonWithItsCatalogId(): void
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
    }

    public function testReimportingTheSameCatalogIsAllowed(): void
    {
        $restaurant = $this->restaurant();
        $catalog = $this->catalog('naofood-catalog-id', 'Naofood');

        $existingTaxon = new Taxon();
        $existingTaxon->setCode('zelty_import_' . $restaurant->getId());
        $existingTaxon->setCurrentLocale('fr');
        $existingTaxon->setZeltyId('naofood-catalog-id');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($existingTaxon);

        $em = $this->em($repository);

        $service = $this->buildService($em);

        // Should not throw.
        $service->import($catalog, $restaurant);

        $this->assertSame('naofood-catalog-id', $existingTaxon->getZeltyId());
    }

    public function testABlankPreExistingTaxonAdoptsTheCurrentCatalogInsteadOfBlocking(): void
    {
        $restaurant = $this->restaurant();
        $catalog = $this->catalog('naofood-catalog-id', 'Naofood');

        // A taxon created before this guard existed: no recorded catalog id.
        $existingTaxon = new Taxon();
        $existingTaxon->setCode('zelty_import_' . $restaurant->getId());
        $existingTaxon->setCurrentLocale('fr');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($existingTaxon);

        $em = $this->em($repository);

        $service = $this->buildService($em);
        $service->import($catalog, $restaurant);

        $this->assertSame('naofood-catalog-id', $existingTaxon->getZeltyId());
    }

    public function testImportingADifferentCatalogForTheSameRestaurantIsRefused(): void
    {
        $restaurant = $this->restaurant();

        $existingTaxon = new Taxon();
        $existingTaxon->setCode('zelty_import_' . $restaurant->getId());
        $existingTaxon->setCurrentLocale('fr');
        $existingTaxon->setZeltyId('naofood-catalog-id');

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($existingTaxon);

        $em = $this->em($repository);

        $wrongCatalog = $this->catalog('click-and-collect-catalog-id', 'Click and Collect');

        $service = $this->buildService($em);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/different catalog/');

        $service->import($wrongCatalog, $restaurant);
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
