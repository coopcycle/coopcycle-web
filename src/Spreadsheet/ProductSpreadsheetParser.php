<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Sylius\Product;
use League\Flysystem\File;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;

class ProductSpreadsheetParser extends AbstractSpreadsheetParser
{
    private $serializer;
    private $productFactory;
    private $variantFactory;
    private $taxCategoryRepository;

    private static $taxCategories = [
        'BASE_STANDARD',
        'BASE_INTERMEDIARY',
        'BASE_REDUCED',
        'BASE_EXEMPT',
    ];

    public function __construct(
        SerializerInterface $serializer,
        ProductFactoryInterface $productFactory,
        ProductVariantFactoryInterface $variantFactory,
        TaxCategoryRepositoryInterface $taxCategoryRepository)
    {
        $this->serializer = $serializer;
        $this->productFactory = $productFactory;
        $this->variantFactory = $variantFactory;
        $this->taxCategoryRepository = $taxCategoryRepository;
    }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): array
    {
        return array_map(function ($data)  {

            $product = $this->productFactory->createNew();

            $uuid = Uuid::uuid4()->toString();

            $product->setCode($uuid);
            $product->setSlug($uuid);

            $product = $this->serializer->denormalize($data, Product::class, 'csv', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $product,
            ]);

            $variant = $this->variantFactory->createForProduct($product);

            $variant->setName($product->getName());
            $variant->setCode(Uuid::uuid4()->toString());
            $variant->setPrice((int) $data['price_tax_incl']);

            $taxCategory = $this->taxCategoryRepository->findOneBy(['name' => $data['tax_category']]);

            if (null === $taxCategory) {

                $taxCategories = $this->taxCategoryRepository->findAll();
                $taxCategoryNames = [];
                foreach ($taxCategories as $tc) {
                    $taxCategoryNames[] = sprintf('"%s"', $tc->getName());
                }

                throw new \Exception(sprintf('The tax category "%s" does not exist. Valid values are %s.',
                    $data['tax_category'], implode(', ', $taxCategoryNames)
                ));
            }

            $variant->setTaxCategory($taxCategory);

            $product->addVariant($variant);

            return $product;

        }, $data);
    }

    public function validateHeader(array $header)
    {
        $expected = [
            'name',
            'price_tax_incl',
            'tax_category',
        ];

        foreach ($expected as $key) {
            if (!in_array($key, $header)) {
                throw new \Exception(sprintf('You must provide a "%s" column', $key));
            }
        }
    }

    public function getExampleData(): array
    {
        $categories = [];
        foreach ($this->taxCategoryRepository->findAll() as $taxCategory) {
            if (in_array($taxCategory->getCode(), self::$taxCategories)) {
                $categories[] = $taxCategory->getName();
            }
        }

        return [
            [
                'name' => 'Pizza Margherita',
                'description' => 'The most famous pizza',
                'price_tax_incl' => 900,
                'tax_category' => $categories[array_rand($categories)],
            ],
            [
                'name' => 'Pizza Regina',
                'description' => 'Another pizza',
                'price_tax_incl' => 1000,
                'tax_category' => $categories[array_rand($categories)],
            ]
        ];
    }
}
