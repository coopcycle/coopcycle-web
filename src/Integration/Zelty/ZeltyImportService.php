<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
use Cocur\Slugify\SlugifyInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;

class ZeltyImportService
{
    public function __construct(
        private ZeltyCatalogParser $parser,
        private ZeltyOptionMapper $optionMapper,
        private ZeltyProductMapper $productMapper,
        private ZeltyTaxonMapper $taxonMapper,
        private ZeltyTaxesMapper $taxesMapper,
        private SlugifyInterface $slugify,
        private LocaleProviderInterface $localeProvider,
        private ?LoggerInterface $logger = null,
    ) {}

    public function import(array $payload, LocalBusiness $restaurant): void
    {
        $this->logger?->info(sprintf('Starting Zelty catalog import for restaurant %d', $restaurant->getId()));

        $catalog = $this->parser->parse($payload);

        $taxCategoryMap = $this->taxesMapper->importTaxes();

        $locale = $this->localeProvider->getDefaultLocaleCode();

        $rootTaxon = $this->createOrGetRootTaxon($restaurant, $catalog, $locale);

        $optionsMap = $this->optionMapper->importOptions(
            $catalog->options,
            $catalog->optionValues,
            $restaurant,
            $locale
        );
        $this->logger?->info(sprintf('Imported %d options', count($optionsMap)));

        $productsMap = $this->productMapper->importDishes(
            $catalog->getDishes(),
            $restaurant,
            $optionsMap,
            $locale,
            $taxCategoryMap,
            $this->taxesMapper->getDefaultTaxCategory()
        );
        $this->logger?->info(sprintf('Imported %d products', count($productsMap)));

        $menuPartsMap = [];
        foreach ($catalog->menuParts as $menuPart) {
            $menuPartsMap[$menuPart->id] = $menuPart;
        }

        $menusMap = $this->taxonMapper->importMenus(
            $catalog->getMenus(),
            $rootTaxon,
            $productsMap,
            $menuPartsMap,
            $locale,
            $restaurant
        );
        $this->logger?->info(sprintf('Imported %d menus', count($menusMap)));

        $this->taxonMapper->importTags(
            $catalog->tags,
            $rootTaxon,
            $productsMap,
            $menusMap,
            $locale
        );
        $this->logger?->info(sprintf('Imported %d tags', count($catalog->tags)));

        $this->logger?->info(sprintf('Completed Zelty catalog import for restaurant %d', $restaurant->getId()));
    }

    private function createOrGetRootTaxon(LocalBusiness $restaurant, $catalog, string $locale): Taxon
    {
        $code = 'zelty_import_' . $restaurant->getId();
        
        $em = $this->taxonMapper->getEntityManager();
        
        $taxon = $em->getRepository(Taxon::class)->findOneBy(['code' => $code]);

        if (null === $taxon) {
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
            
            if (!$restaurant->getTaxons()->contains($taxon)) {
                $restaurant->addTaxon($taxon);
            }
            
            return $taxon;
        }

        if (!$restaurant->getTaxons()->contains($taxon)) {
            $restaurant->addTaxon($taxon);
        }

        return $taxon;
    }
}
