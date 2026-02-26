<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
use Psr\Log\LoggerInterface;

class ZeltyImportService
{
    public function __construct(
        private ZeltyCatalogParser $parser,
        private ZeltyOptionMapper $optionMapper,
        private ZeltyProductMapper $productMapper,
        private ZeltyTaxonMapper $taxonMapper,
        private ZeltyTaxesMapper $taxesMapper,
        private ?LoggerInterface $logger = null,
    ) {}

    public function import(array $payload, LocalBusiness $restaurant): void
    {
        $this->logger?->info(sprintf('Starting Zelty catalog import for restaurant %d', $restaurant->getId()));

        $catalog = $this->parser->parse($payload);

        $taxCategoryMap = $this->taxesMapper->importTaxes();

        $locale = $catalog->locale ?? 'en';

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
            $restaurant,
            $productsMap,
            $menuPartsMap,
            $locale
        );
        $this->logger?->info(sprintf('Imported %d menus', count($menusMap)));

        $this->taxonMapper->importTags(
            $catalog->tags,
            $restaurant,
            $productsMap,
            $menusMap,
            $locale
        );
        $this->logger?->info(sprintf('Imported %d tags', count($catalog->tags)));

        $this->logger?->info(sprintf('Completed Zelty catalog import for restaurant %d', $restaurant->getId()));
    }
}
