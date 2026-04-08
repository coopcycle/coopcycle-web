<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\DataType\NumRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Integration\Zelty\Dto\ZeltyOption;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionValue;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maps Zelty options and option values to Sylius product options.
 */
class ZeltyOptionMapper
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Import all options and their values.
     *
     * @param array<ZeltyOption> $options Array of ZeltyOption objects
     * @param array<ZeltyOptionValue> $optionValues Array of ZeltyOptionValue objects
     * @param LocalBusiness $restaurant The restaurant
     * @param string $locale The locale code
     * @return array<int,ProductOption> Map of option codes/IDs to ProductOption entities
     */
    public function importOptions(
        array $options,
        array $optionValues,
        LocalBusiness $restaurant,
        string $locale
    ): array {
        $optionValueMap = $this->indexOptionValuesById($optionValues);
        $optionMap = [];

        foreach ($options as $zeltyOption) {
            $option = $this->importOption($zeltyOption, $restaurant, $locale);
            $optionMap[$option->getCode()] = $option;
            $optionMap[$zeltyOption->id] = $option;

            $this->importOptionValuesForOption($option, $zeltyOption, $optionValueMap, $locale);
        }

        return $optionMap;
    }

    /**
     * Index option values by their ID.
     * @param array<ZeltyOption> $optionValues
     *
     * @return array<int, ZeltyOptionValue>
     */
    private function indexOptionValuesById(array $optionValues): array
    {
        $optionValueMap = [];
        foreach ($optionValues as $zeltyValue) {
            $optionValueMap[$zeltyValue->id] = $zeltyValue;
        }
        return $optionValueMap;
    }

    /**
     * Import all values for a given option.
     */
    private function importOptionValuesForOption(
        ProductOption $option,
        ZeltyOption $zeltyOption,
        array $optionValueMap,
        string $locale,
        ?Product $product = null
    ): void {
        foreach ($zeltyOption->valueIds as $valueId) {
            if (!isset($optionValueMap[$valueId])) {
                continue;
            }

            $this->importOptionValue($optionValueMap[$valueId], $option, $locale, $product);
        }
    }

    /**
     * Get an option by its code.
     */
    public function getOptionByCode(string $code): ?ProductOptionInterface
    {
        return $this->em->getRepository(ProductOption::class)->findOneBy(['code' => $code]);
    }

    /**
     * Import a single option.
     */
    private function importOption(ZeltyOption $zeltyOption, LocalBusiness $restaurant, string $locale): ProductOption
    {
        $optionCode = $this->generateOptionCode($zeltyOption->id, $restaurant);
        $option = $this->findOptionByCodeAndRestaurant($optionCode, $restaurant);

        //TODO: Implement upsert
        if ($option !== null) {
            return $option;
        }

        return $this->createOption($zeltyOption, $restaurant, $locale, $optionCode);
    }

    /**
     * Generate a unique option code combining Zelty ID and restaurant ID.
     */
    private function generateOptionCode(string $zeltyOptionId, LocalBusiness $restaurant): string
    {
        return sprintf('%s_%d', $zeltyOptionId, $restaurant->getId());
    }

    /**
     * Find an option by code and restaurant.
     */
    private function findOptionByCodeAndRestaurant(string $optionCode, LocalBusiness $restaurant): ?ProductOption
    {
        return $this->em->getRepository(ProductOption::class)->findOneBy([
            'code' => $optionCode,
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Create a new option.
     */
    private function createOption(
        ZeltyOption $zeltyOption,
        LocalBusiness $restaurant,
        string $locale,
        string $optionCode
    ): ProductOption {
        $option = new ProductOption();
        $option->setCode($optionCode);
        $option->setRestaurant($restaurant);
        $option->setCurrentLocale($locale);
        $option->setValuesRange($this->createChoicesRange($zeltyOption));

        if ($zeltyOption->name) {
            $option->setName($zeltyOption->name);
        }

        //FIXME: Should persist at the end or option import loop
        $this->em->persist($option);
        $this->em->flush();

        return $option;
    }

    /**
     * Create a NumRange for option choices.
     */
    private function createChoicesRange(ZeltyOption $zeltyOption): NumRange
    {
        return (new NumRange())
            ->setLower($zeltyOption->min_choices)
            ->setUpper($zeltyOption->max_choices);
    }

    /**
     * Import a single option value.
     */
    private function importOptionValue(ZeltyOptionValue $zeltyValue, ProductOption $option, string $locale, ?Product $product = null): ProductOptionValue
    {
        $valueCode = $this->generateOptionValueCode($zeltyValue->id, $option);
        $value = $this->findOptionValueByCode($option, $valueCode);

        if ($value !== null) {
            return $value;
        }

        return $this->createOptionValue($zeltyValue, $option, $locale, $valueCode, $product);
    }

    /**
     * Generate a unique option value code.
     */
    private function generateOptionValueCode(string $zeltyValueId, ProductOption $option): string
    {
        return sprintf('%s_%d', $zeltyValueId, $option->getRestaurant()->getId());
    }

    /**
     * Find an existing option value by code within the option.
     *
     * @return ProductOptionValue|null
     */
    private function findOptionValueByCode(ProductOption $option, string $valueCode): ?ProductOptionValue
    {
        foreach ($option->getValues() as $existingValue) {
            if ($existingValue->getCode() === $valueCode) {
                /** @var ProductOptionValue */
                return $existingValue;
            }
        }
        return null;
    }

    /**
     * Create a new option value.
     */
    private function createOptionValue(
        ZeltyOptionValue $zeltyValue,
        ProductOption $option,
        string $locale,
        string $valueCode,
        ?Product $product = null
    ): ProductOptionValue {
        $value = new ProductOptionValue();
        $value->setCode($valueCode);
        $value->setZeltyCode($zeltyValue->id);
        $value->setCurrentLocale($locale);
        $value->setValue($zeltyValue->name);

        if ($product !== null) {
            $value->setProduct($product);
        }

        if ($zeltyValue->price && $zeltyValue->price->price > 0) {
            $option->setStrategy(ProductOptionInterface::STRATEGY_OPTION_VALUE);
            $value->setPrice($zeltyValue->price->price);
        }

        $value->setEnabled(!$zeltyValue->disabled);

        $option->addValue($value);
        $this->em->persist($value);
        $this->em->flush();

        return $value;
    }
}
