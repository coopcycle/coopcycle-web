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
     * @return array<string, ProductOption> Map of option codes/IDs to ProductOption entities
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
     * @param array<ZeltyOptionValue> $optionValues
     *
     * @return array<string, ZeltyOptionValue>
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

        $this->em->persist($option);

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
        $value = $this->findOptionValueByCode($valueCode);

        if ($value !== null) {
            $value->setZeltyId($zeltyValue->id);
            $value->setZeltyInternalId($zeltyValue->internalId);

            // Zelty lets the same option value be reused across several option
            // groups, but a ProductOptionValue can only belong to one ProductOption
            // (option_id is a required FK). Re-attaching it here — instead of only
            // looking in $option's own collection — is what stops the second option
            // from trying to INSERT a row with an already-taken code.
            if (!$option->getValues()->contains($value)) {
                $option->addValue($value);
            }

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
     * Find an existing option value by code, across the whole catalog rather than
     * just the option passed in: the code (Zelty value id + restaurant id) is
     * globally unique in the database, regardless of which option currently owns
     * the row, so that is the scope a lookup has to use to avoid a duplicate insert.
     *
     * @return ProductOptionValue|null
     */
    private function findOptionValueByCode(string $valueCode): ?ProductOptionValue
    {
        // Values belonging to a disabled option/product are hidden by the global
        // DisabledFilter, which would otherwise make an existing row invisible here
        // and lead to the same duplicate insert.
        return $this->withoutDisabledFilter(fn () =>
            $this->em->getRepository(ProductOptionValue::class)->findOneBy([
                'code' => $valueCode,
            ])
        );
    }

    /**
     * Temporarily disable the global DisabledFilter for a lookup that must see
     * every row regardless of enabled state.
     */
    private function withoutDisabledFilter(callable $callback)
    {
        $filters = $this->em->getFilters();
        $filterActive = $filters->isEnabled('disabled_filter');

        if ($filterActive) {
            $filters->disable('disabled_filter');
        }

        try {
            return $callback();
        } finally {
            if ($filterActive) {
                $filters->enable('disabled_filter');
            }
        }
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
        $value->setZeltyId($zeltyValue->id);
        $value->setZeltyInternalId($zeltyValue->internalId);
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

        return $value;
    }
}
