<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;

class ZeltyImportService
{
    public function __construct(
        private ZeltyCatalogParser $parser,
        private ZeltyOptionMapper $optionMapper,
        private ZeltyProductMapper $productMapper,
        private ZeltyTaxonMapper $taxonMapper,
        private ZeltyTaxesMapper $taxesMapper,
    ) {}

    public function import(array $payload, LocalBusiness $restaurant): void
    {
        $catalog = $this->parser->parse($payload);

        $taxCategoryMap = $this->taxesMapper->importTaxes();

        $locale = $catalog->locale ?? 'en';

        $optionsMap = $this->optionMapper->importOptions(
            $catalog->options,
            $catalog->optionValues,
            $restaurant,
            $locale
        );

        $productsMap = $this->productMapper->importDishes(
            $catalog->getDishes(),
            $restaurant,
            $optionsMap,
            $locale,
            $taxCategoryMap
        );

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

        $this->taxonMapper->importTags(
            $catalog->tags,
            $restaurant,
            $productsMap,
            $menusMap,
            $locale
        );
    }
}
