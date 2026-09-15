<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;

/**
 * Service responsible for orchestrating the full Zelty catalog import.
 */
class ZeltyImportService
{
    public function __construct(
        private ZeltyOptionMapper $optionMapper,
        private ZeltyProductMapper $productMapper,
        private ZeltyMenuMapper $menuMapper,
        private ZeltyTaxonMapper $taxonMapper,
        private ZeltyTaxesMapper $taxesMapper,
        private SlugifyInterface $slugify,
        private LocaleProviderInterface $localeProvider,
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Import a complete Zelty catalog for a restaurant.
     */
    public function import(ZeltyCatalog $catalog, LocalBusiness $restaurant): void
    {
        if (!$restaurant->hasZeltyApiKey()) {
            throw new \Exception(sprintf('No Zelty key set for business: %s', $restaurant->getName()));
        }

        $this->logInfo(sprintf('Starting Zelty catalog import for restaurant %d', $restaurant->getId()));

        $locale = $this->localeProvider->getDefaultLocaleCode();

        $this->taxesMapper->reset();
        $this->taxesMapper->setRestaurant($restaurant);
        $taxCategoryMap = $this->taxesMapper->importTaxes();

        $rootTaxon = $this->createOrGetRootTaxon($restaurant, $catalog, $locale);
        $optionsMap = $this->importOptions($catalog, $restaurant, $locale);
        $productsMap = $this->importDishes($catalog, $restaurant, $optionsMap, $locale, $taxCategoryMap);
        $menusMap = $this->importMenus($catalog, $restaurant, $locale, $productsMap, $optionsMap, $taxCategoryMap);

        $importedCodes = array_merge(array_keys($productsMap), array_keys($menusMap));
        $this->disableRemovedProducts($restaurant, $importedCodes);

        $allItemsMap = array_merge($productsMap, $menusMap);
        $this->taxonMapper->importTags($catalog->tags, $rootTaxon, $allItemsMap, $locale);

        $this->logInfo(sprintf('Completed Zelty catalog import for restaurant %d', $restaurant->getId()));
    }

    private function disableRemovedProducts(LocalBusiness $restaurant, array $importedCodes): void
    {
        $stale = $this->productRepository->findZeltyProductsForRestaurantNotIn($restaurant, $importedCodes);

        foreach ($stale as $product) {
            $product->setEnabled(false);
        }

        if (!empty($stale)) {
            $this->logInfo(sprintf('Disabled %d products removed from Zelty catalog', count($stale)));
        }
    }

    /**
     * Log an info message if logger is available.
     */
    private function logInfo(string $message): void
    {
        $this->logger?->info($message);
    }

    /**
     * Import all options from the catalog.
     *
     * @return array Map of option identifiers to option entities
     */
    private function importOptions(ZeltyCatalog $catalog, LocalBusiness $restaurant, string $locale): array
    {
        $optionsMap = $this->optionMapper->importOptions(
            $catalog->options,
            $catalog->optionValues,
            $restaurant,
            $locale
        );

        $this->logInfo(sprintf('Imported %d options', count($optionsMap)));

        return $optionsMap;
    }

    /**
     * Import all dishes/products from the catalog.
     *
     * @return array Map of product codes to product entities
     */
    private function importDishes(
        ZeltyCatalog $catalog,
        LocalBusiness $restaurant,
        array $optionsMap,
        string $locale,
        array $taxCategoryMap
    ): array {
        $productsMap = $this->productMapper->importDishes(
            $catalog->getDishes(),
            $restaurant,
            $optionsMap,
            $locale,
            $taxCategoryMap,
            $this->taxesMapper->getDefaultTaxCategory()
        );

        $this->logInfo(sprintf('Imported %d products', count($productsMap)));

        return $productsMap;
    }

    /**
     * Import all menus from the catalog.
     *
     * @return array Map of menu IDs to menu product entities
     */
    private function importMenus(
        ZeltyCatalog $catalog,
        LocalBusiness $restaurant,
        string $locale,
        array $productsMap,
        array $optionsMap,
        array $taxCategoryMap
    ): array {
        $menuPartsMap = $this->indexMenuPartsById($catalog->menuParts);

        $menusMap = $this->menuMapper->importMenus(
            $catalog->getMenus(),
            $menuPartsMap,
            $productsMap,
            $optionsMap,
            $restaurant,
            $locale,
            $taxCategoryMap,
            $this->taxesMapper->getDefaultTaxCategory(),
            $this->taxesMapper->getOrderedTaxCategories()
        );

        $this->logInfo(sprintf('Imported %d menus', count($menusMap)));

        return $menusMap;
    }

    /**
     * Index menu parts by their ID for quick lookup.
     *
     * @param array $menuParts Array of menu part objects
     * @return array<string, object> Map of menu part ID to menu part
     */
    private function indexMenuPartsById(array $menuParts): array
    {
        $menuPartsMap = [];
        foreach ($menuParts as $menuPart) {
            $menuPartsMap[$menuPart->id] = $menuPart;
        }
        return $menuPartsMap;
    }

    /**
     * Create or retrieve the root taxon for imported catalog.
     */
    private function createOrGetRootTaxon(LocalBusiness $restaurant, ZeltyCatalog $catalog, string $locale): Taxon
    {
        $code = 'zelty_import_' . $restaurant->getId();

        $taxon = $this->em->getRepository(Taxon::class)->findOneBy(['code' => $code]);

        if ($taxon === null) {
            $taxon = $this->createRootTaxon($restaurant, $catalog, $locale, $code, $this->em);
        } else {
            $this->guardAgainstCatalogMismatch($taxon, $catalog, $restaurant);
        }

        $this->ensureRestaurantHasTaxon($restaurant, $taxon);

        return $taxon;
    }

    /**
     * The root taxon's code is keyed only by restaurant ID, not by which
     * Zelty catalog built it — so importing a *different* catalog for the
     * same restaurant (the wrong one selected on Zelty's side, or picked by
     * mistake on the admin's manual pull screen) would otherwise silently
     * reuse and rewrite this same taxon, mixing tags/menus from two
     * unrelated catalogs together under one tree with no trace of the mix-up.
     *
     * A taxon created before this guard existed has no recorded catalog id
     * yet; its first import after this fix adopts the current catalog as the
     * taxon's source of truth instead of blocking it outright.
     */
    private function guardAgainstCatalogMismatch(Taxon $taxon, ZeltyCatalog $catalog, LocalBusiness $restaurant): void
    {
        if (!$taxon->hasZeltyId()) {
            $taxon->setZeltyId($catalog->id);

            return;
        }

        if ($taxon->getZeltyId() !== $catalog->id) {
            throw new \RuntimeException(sprintf(
                'Refusing to import Zelty catalog "%s" (%s) for restaurant %d: ' .
                'its existing catalog taxon was built from a different catalog (%s). ' .
                'Importing would silently mix two unrelated catalogs together.',
                $catalog->name ?? $catalog->id,
                $catalog->id,
                $restaurant->getId(),
                $taxon->getZeltyId()
            ));
        }
    }

    /**
     * Create a new root taxon for the imported catalog.
     */
    private function createRootTaxon(
        LocalBusiness $restaurant,
        ZeltyCatalog $catalog,
        string $locale,
        string $code,
        $em
    ): Taxon {
        $taxon = new Taxon();
        $taxon->setCode($code);
        $taxon->setCurrentLocale($locale);
        $taxon->setZeltyId($catalog->id);

        $slug = sprintf('imported-catalog-from-zelty-%d', $restaurant->getId());
        $taxon->setSlug($this->slugify->slugify($slug));

        $name = $catalog->name ?? 'Imported catalog from Zelty';
        $taxon->setName($name);
        $taxon->setEnabled(true);

        $em->persist($taxon);

        return $taxon;
    }

    private function ensureRestaurantHasTaxon(LocalBusiness $restaurant, Taxon $taxon): void
    {
        if (!$restaurant->getTaxons()->contains($taxon)) {
            $restaurant->addTaxon($taxon);
        }
    }
}
