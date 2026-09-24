<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\DataType\NumRange;
use AppBundle\Entity\LocalBusiness;
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
            $this->disableRemovedOptionValues($option, $zeltyOption);
        }

        return $optionMap;
    }

    /**
     * A value still listed in the option's value_ids gets refreshed by
     * importOptionValuesForOption() above — but nothing ever revisited a
     * value removed from an option entirely (e.g. the same Zelty option id
     * offering a different set of values per catalog — "Frites au cheddar"
     * exists under "Choix des frites" in the Click and Collect catalog but
     * not in Naofood's), so it lingered forever, still enabled, no longer
     * reflecting what this option actually offers today.
     *
     * Unlike the equivalent tag-taxon cleanup, disabling genuinely works
     * here: ProductOptionValue::enabled is covered by the global
     * DisabledFilter, so a disabled value disappears from display on its
     * own — no need to detach or remove anything.
     */
    private function disableRemovedOptionValues(ProductOption $option, ZeltyOption $zeltyOption): void
    {
        $currentValueIds = array_flip($zeltyOption->valueIds);

        foreach ($option->getValues() as $value) {
            if (!$value instanceof ProductOptionValue) {
                continue;
            }

            $zeltyId = $value->getZeltyId();

            if ($zeltyId === null || isset($currentValueIds[$zeltyId])) {
                continue;
            }

            $value->setEnabled(false);
        }
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
        string $locale
    ): void {
        foreach ($zeltyOption->valueIds as $valueId) {
            if (!isset($optionValueMap[$valueId])) {
                continue;
            }

            $this->importOptionValue($optionValueMap[$valueId], $option, $locale);
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

        if ($option === null) {
            return $this->createOption($zeltyOption, $restaurant, $locale, $optionCode);
        }

        // Re-apply on every import, not just creation: minimum_choices/maximum_choices
        // control whether the option is mandatory (lower > 0) — a value frozen at
        // creation time would never follow a restaurant fixing this in Zelty, or an
        // option that was first synced with the wrong min/max.
        $option->setValuesRange($this->createChoicesRange($zeltyOption));
        $option->setAdditional($this->resolveAdditional($zeltyOption));

        if ($zeltyOption->name) {
            $option->setName($zeltyOption->name);
        }

        return $option;
    }

    /**
     * Whether this option should go through Sylius/CoopCycle's "additional"
     * option code path — a checkbox/quantity-stepper UI whose validity is
     * driven entirely by valuesRange (min/max) — versus the plain radio-style
     * path, which is hardcoded to always require exactly one selection
     * regardless of valuesRange (see js/.../useProductOptions.js:isMandatory/
     * isValid: `if (!option.additional) return true / totalQuantity > 0`).
     *
     * The radio path's hardcoded "always mandatory" only matches Zelty's own
     * semantics for a genuinely mandatory single pick (minimum_choices >= 1
     * and maximum_choices <= 1). Everything else — an optional single pick
     * like "Choix des frites" (min 0, max 1), or any multi-pick group like
     * "Sauce supplémentaire frites" (min 0, max 5) — needs the additional
     * path so valuesRange is actually honored instead of ignored.
     */
    private function resolveAdditional(ZeltyOption $zeltyOption): bool
    {
        return !($zeltyOption->min_choices >= 1 && $zeltyOption->max_choices <= 1);
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
        $option->setAdditional($this->resolveAdditional($zeltyOption));

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
    private function importOptionValue(ZeltyOptionValue $zeltyValue, ProductOption $option, string $locale): ProductOptionValue
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

            $this->applyZeltyState($value, $zeltyValue, $option, $locale);

            return $value;
        }

        return $this->createOptionValue($zeltyValue, $option, $locale, $valueCode);
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
        string $valueCode
    ): ProductOptionValue {
        $value = new ProductOptionValue();
        $value->setCode($valueCode);
        $value->setZeltyId($zeltyValue->id);
        $value->setZeltyInternalId($zeltyValue->internalId);

        $this->applyZeltyState($value, $zeltyValue, $option, $locale);

        $option->addValue($value);
        $this->em->persist($value);

        return $value;
    }

    /**
     * Everything Zelty owns about a choice — its label, its price and whether
     * it is offered at all — applied the same way whether the value is being
     * created or refreshed.
     *
     * Applying it on *every* import is the point: these used to be written
     * only at creation, so a choice renamed or repriced in Zelty kept its
     * original label and price here forever, and one switched off by accident
     * (DisabledProductListener following a wrong product link, say) could
     * never come back on. Same convention as the option's own min/max choices
     * and the variant prices in ZeltyProductMapper.
     */
    private function applyZeltyState(
        ProductOptionValue $value,
        ZeltyOptionValue $zeltyValue,
        ProductOption $option,
        string $locale
    ): void {
        $value->setCurrentLocale($locale);

        // Guarded like the option's own name: a nameless value in the payload
        // means "nothing to say about it", not "clear the label we have".
        if ($zeltyValue->name) {
            $value->setValue($zeltyValue->name);
        }

        $price = $zeltyValue->price?->price ?? 0;

        // The strategy is only ever switched *on*: a priced sibling in the same
        // group is what makes the whole option per-value priced, so one choice
        // dropping back to zero must not take the group down with it.
        if ($price > 0) {
            $option->setStrategy(ProductOptionInterface::STRATEGY_OPTION_VALUE);
        }

        $value->setPrice($price);
        $value->setEnabled(!$zeltyValue->disabled);
    }
}
