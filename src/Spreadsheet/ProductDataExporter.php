<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Sylius\Product\ProductVariantInterface;
use League\Csv\Writer as CsvWriter;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

/**
 * Exports products using the same columns as the file expected by ProductSpreadsheetParser,
 * so that the exported file can be imported again (in the same or in another restaurant).
 */
final class ProductDataExporter
{
    public function __construct(private ProductVariantResolverInterface $variantResolver)
    {}

    /**
     * @param iterable<ProductInterface> $products
     */
    public function export(iterable $products): string
    {
        $csv = CsvWriter::fromString('');
        $csv->insertOne([
            'name',
            'description',
            'price_tax_incl',
            'tax_category',
        ]);

        foreach ($products as $product) {

            /** @var ProductVariantInterface|null $variant */
            $variant = $this->variantResolver->getVariant($product);

            $csv->insertOne([
                $product->getName(),
                $product->getDescription(),
                $variant?->getPrice(),
                $variant?->getTaxCategory()?->getName(),
            ]);
        }

        return $csv->toString();
    }
}
