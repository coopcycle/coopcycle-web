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

        $this->stampCatalogOwnership($catalog, $productsMap, $menusMap);

        $importedCodes = array_merge(array_keys($productsMap), array_keys($menusMap));
        $this->disableRemovedProducts($restaurant, $catalog, $importedCodes);

        $allItemsMap = array_merge($productsMap, $menusMap);
        $this->taxonMapper->importTags($catalog->tags, $rootTaxon, $allItemsMap, $locale);

        $this->logInfo(sprintf('Completed Zelty catalog import for restaurant %d', $restaurant->getId()));
    }

    /**
     * Record which catalog every imported product belongs to, so that a
     * later import of *another* catalog for the same restaurant can tell
     * the two apart instead of treating everything it did not just import
     * as removed.
     *
     * @param array<string, \AppBundle\Entity\Sylius\Product> $productsMap
     * @param array<string, \AppBundle\Entity\Sylius\Product> $menusMap
     */
    private function stampCatalogOwnership(ZeltyCatalog $catalog, array $productsMap, array $menusMap): void
    {
        foreach (array_merge($productsMap, $menusMap) as $product) {
            $product->setZeltyCatalogId($catalog->id);
        }
    }

    private function disableRemovedProducts(LocalBusiness $restaurant, ZeltyCatalog $catalog, array $importedCodes): void
    {
        $stale = $this->productRepository->findZeltyProductsForRestaurantNotIn($restaurant, $catalog->id, $importedCodes);

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
     * Create or retrieve the root taxon for the imported catalog.
     *
     * The code is keyed by restaurant *and* catalog: a restaurant's Zelty
     * account can hold several catalogs ("Click&Collect", a copy of it for
     * another channel...), and each one deserves its own menu tree. Keying
     * on the restaurant alone used to force every catalog through a single
     * taxon, so importing a second one either mixed both trees together or,
     * once that was guarded against, dead-ended the shop on whichever
     * catalog happened to be imported first — including an empty one pulled
     * by mistake.
     *
     * A restaurant imported before this became per-catalog still has a taxon
     * under the legacy restaurant-only code; it is kept (renaming it would
     * strand the shop's active menu pointer) and adopted as the tree for the
     * catalog that built it.
     */
    private function createOrGetRootTaxon(LocalBusiness $restaurant, ZeltyCatalog $catalog, string $locale): Taxon
    {
        $repository = $this->em->getRepository(Taxon::class);

        $code = self::rootTaxonCode($restaurant, $catalog);

        $taxon = $repository->findOneBy(['code' => $code]);

        if ($taxon === null) {
            $taxon = $this->findLegacyRootTaxon($repository, $restaurant, $catalog);
        }

        if ($taxon === null) {
            $taxon = $this->createRootTaxon($restaurant, $catalog, $locale, $code, $this->em);
        }

        $this->ensureRestaurantHasTaxon($restaurant, $taxon);

        return $taxon;
    }

    private static function rootTaxonCode(LocalBusiness $restaurant, ZeltyCatalog $catalog): string
    {
        // The restaurant id stays in the code even though a Zelty catalog id
        // is a uuid: two shops on the same instance sharing a Zelty account
        // would otherwise pull the same catalog into one shared taxon.
        return sprintf('zelty_import_%d_%s', $restaurant->getId(), $catalog->id);
    }

    /**
     * The taxon a pre-multi-catalog import left behind, if it belongs to the
     * catalog being imported. A taxon created before catalog ids were
     * recorded at all has no id to compare against, so it adopts the current
     * catalog rather than being left orphaned next to a fresh duplicate of
     * the same tree.
     *
     * A legacy taxon built from a *different* catalog is simply not ours:
     * this import gets its own taxon and leaves that one untouched.
     */
    private function findLegacyRootTaxon($repository, LocalBusiness $restaurant, ZeltyCatalog $catalog): ?Taxon
    {
        $taxon = $repository->findOneBy(['code' => 'zelty_import_' . $restaurant->getId()]);

        if ($taxon === null) {
            return null;
        }

        if (!$taxon->hasZeltyId()) {
            $taxon->setZeltyId($catalog->id);

            return $taxon;
        }

        return $taxon->getZeltyId() === $catalog->id ? $taxon : null;
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

        // Taxon slugs are unique per locale, so the catalog id belongs here
        // too: without it a restaurant's second catalog would collide with
        // the first one's root taxon on insert.
        $slug = sprintf('imported-catalog-from-zelty-%d-%s', $restaurant->getId(), $catalog->id);
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
