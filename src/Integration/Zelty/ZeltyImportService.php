<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
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
        private ZeltyCatalogParser $parser,
        private ZeltyOptionMapper $optionMapper,
        private ZeltyProductMapper $productMapper,
        private ZeltyMenuMapper $menuMapper,
        private ZeltyTaxonMapper $taxonMapper,
        private ZeltyTaxesMapper $taxesMapper,
        private SlugifyInterface $slugify,
        private LocaleProviderInterface $localeProvider,
        private EntityManagerInterface $em,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Import a complete Zelty catalog for a restaurant.
     *
     * @param array $payload The raw Zelty catalog payload
     * @param LocalBusiness $restaurant The restaurant to import into
     */
    public function import(array $payload, LocalBusiness $restaurant): void
    {
        $this->logInfo(sprintf('Starting Zelty catalog import for restaurant %d', $restaurant->getId()));

        $catalog = $this->parser->parse($payload);
        $locale = $this->localeProvider->getDefaultLocaleCode();
        $taxCategoryMap = $this->taxesMapper->importTaxes();

        $rootTaxon = $this->createOrGetRootTaxon($restaurant, $catalog, $locale);
        $optionsMap = $this->importOptions($catalog, $restaurant, $locale);
        $productsMap = $this->importDishes($catalog, $restaurant, $optionsMap, $locale, $taxCategoryMap);
        $menusMap = $this->importMenus($catalog, $restaurant, $locale, $productsMap, $taxCategoryMap);

        $this->createOrGetMenusTaxon($restaurant, $rootTaxon, $locale, $menusMap);
        $this->taxonMapper->importTags($catalog->tags, $rootTaxon, $productsMap, $locale);

        $this->logInfo(sprintf('Completed Zelty catalog import for restaurant %d', $restaurant->getId()));
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
        array $taxCategoryMap
    ): array {
        $menuPartsMap = $this->indexMenuPartsById($catalog->menuParts);

        $menusMap = $this->menuMapper->importMenus(
            $catalog->getMenus(),
            $menuPartsMap,
            $productsMap,
            $restaurant,
            $locale,
            $this->taxesMapper->getDefaultTaxCategory()
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
        $code = $this->generateRootTaxonCode($restaurant);

        $taxon = $this->em->getRepository(Taxon::class)->findOneBy(['code' => $code]);

        if ($taxon === null) {
            $taxon = $this->createRootTaxon($restaurant, $catalog, $locale, $code, $this->em);
        }

        $this->ensureRestaurantHasTaxon($restaurant, $taxon);

        return $taxon;
    }

    /**
     * Generate the code for the root import taxon.
     */
    private function generateRootTaxonCode(LocalBusiness $restaurant): string
    {
        return 'zelty_import_' . $restaurant->getId();
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

        $slug = sprintf('imported-catalog-from-zelty-%d', $restaurant->getId());
        $taxon->setSlug($this->slugify->slugify($slug));

        $name = $catalog->name ?? 'Imported catalog from Zelty';
        $taxon->setName($name);
        $taxon->setEnabled(true);

        $em->persist($taxon);
        $em->flush();

        return $taxon;
    }

    /**
     * Ensure the restaurant has the given taxon associated.
     */
    private function ensureRestaurantHasTaxon(LocalBusiness $restaurant, Taxon $taxon): void
    {
        if (!$restaurant->getTaxons()->contains($taxon)) {
            $restaurant->addTaxon($taxon);
        }
    }

    /**
     * Create or retrieve the menus taxon with all menu products linked.
     */
    private function createOrGetMenusTaxon(
        LocalBusiness $restaurant,
        Taxon $rootTaxon,
        string $locale,
        array $menusMap
    ): Taxon {
        $code = 'zelty_menus_' . $restaurant->getId();

        $taxon = $this->em->getRepository(Taxon::class)->findOneBy(['code' => $code]);

        if ($taxon === null) {
            $taxon = $this->createMenusTaxon($restaurant, $rootTaxon, $locale, $code, $this->em);
        }

        $this->linkMenuProductsToTaxon($this->em, $menusMap, $taxon);

        return $taxon;
    }

    /**
     * Create a new menus taxon.
     */
    private function createMenusTaxon(
        LocalBusiness $restaurant,
        Taxon $rootTaxon,
        string $locale,
        string $code,
        $em
    ): Taxon {
        $taxon = new Taxon();
        $taxon->setCode($code);
        $taxon->setCurrentLocale($locale);
        $taxon->setSlug($this->slugify->slugify('nos-menus-' . $restaurant->getId()));
        $taxon->setName('Nos Menus');
        $taxon->setEnabled(true);
        $taxon->setParent($rootTaxon);

        $em->persist($taxon);
        $em->flush();

        return $taxon;
    }

    /**
     * Link all menu products to the menus taxon.
     */
    private function linkMenuProductsToTaxon($em, array $menusMap, Taxon $taxon): void
    {
        foreach ($menusMap as $menuProduct) {
            $productTaxon = new ProductTaxon();
            $productTaxon->setProduct($menuProduct);
            $productTaxon->setTaxon($taxon);
            $productTaxon->setPosition(0);
            $em->persist($productTaxon);
        }

        $em->flush();
    }
}
