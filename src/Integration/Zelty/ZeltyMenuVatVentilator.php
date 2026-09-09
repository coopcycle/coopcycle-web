<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;

/**
 * Implements the VAT "ventilation" (distribution) required by French tax law
 * for a bundled sale covering several rates — see
 * https://bofip.impots.gouv.fr/bofip/256-PGP.html/identifiant%3DBOI-TVA-LIQ-30-20-10-20-20140924#Ventilation_21
 *
 * BOFiP allows several methods; this implements the "menu vs. à la carte"
 * one: for each menu part, compare the à la carte (standalone) price of the
 * chosen dish, excluding tax, to the sum of all chosen dishes' à la carte
 * prices, and apply that ratio to the bundle's actual sold price to get each
 * rate's share.
 *
 * Scope: Zelty-imported menus only (product code starting with "ZM", per
 * this integration's existing convention — see ZeltyImportService). Without
 * this, ZeltyMenuMapper::resolveTaxCategory() taxes the whole menu at its
 * highest-rated component — which BOFiP says is exactly the correct
 * behavior *absent* a ventilation, so that heuristic remains the fallback
 * whenever ventilation can't be computed (missing price/category data,
 * division by zero, anything unresolvable) — never a bare failure.
 */
class ZeltyMenuVatVentilator
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaxRateResolverInterface $taxRateResolver,
        private CalculatorInterface $calculator,
        private string $region,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return array<int, array{variant: ProductVariant, amount: int}>|null
     *   One slice per distinct tax rate represented among the menu's chosen
     *   components, amounts summing to $orderItem->getTotal(); or null when
     *   ventilation doesn't apply or can't be computed, in which case the
     *   caller should fall back to its normal single-category handling.
     */
    public function ventilate(OrderItemInterface $orderItem): ?array
    {
        $variant = $orderItem->getVariant();

        if (!$variant instanceof ProductVariant) {
            return null;
        }

        $product = $variant->getProduct();

        if (!$product instanceof Product || !$this->isZeltyMenu($product)) {
            return null;
        }

        $components = $this->resolveComponents($variant);

        if ($components === null || count($components) < 2) {
            // Nothing to ventilate: unresolvable, or a single component
            // (already correctly taxed by the normal single-category path).
            return null;
        }

        $totalReferenceExclTax = array_sum(array_column($components, 'referenceExclTax'));

        if ($totalReferenceExclTax <= 0) {
            return null;
        }

        return $this->allocate($orderItem->getTotal(), $components, $totalReferenceExclTax);
    }

    private function isZeltyMenu(Product $product): bool
    {
        $zeltyId = $product->getZeltyId();

        return $zeltyId !== null && str_starts_with($zeltyId, 'ZM');
    }

    /**
     * Resolve each chosen menu-part dish to its own reference variant, rate,
     * and à la carte price excluding tax.
     *
     * Deliberately restricted to option values whose option is not
     * "additional" (see ZeltyMenuMapper::createMenuPartOption() — a menu
     * part is always imported as a mandatory single pick, additional=false).
     * That excludes priced "extra" selections (e.g. a paid supplement sauce)
     * from the ventilation base, since those are surcharges on top of the
     * menu's fixed price, not one of the fixed components the price is being
     * split across, and are already taxed via their own path — mixing them
     * in here would double-count.
     *
     * @return array<int, array{variant: ProductVariant, referenceExclTax: int}>|null
     */
    private function resolveComponents(ProductVariant $variant): ?array
    {
        $components = [];

        foreach ($variant->getOptionValues() as $optionValue) {
            if (!$optionValue instanceof ProductOptionValue) {
                continue;
            }

            $option = $optionValue->getOption();

            if ($option instanceof ProductOption && $option->isAdditional()) {
                continue;
            }

            $component = $this->resolveComponent($optionValue);

            if ($component === null) {
                // One unresolvable component (deleted dish, missing price,
                // missing category…) means we can't ventilate faithfully at
                // all — bail out entirely rather than silently mis-tax part
                // of the bundle. The caller falls back to the existing
                // highest-rate default, which is what BOFiP mandates when no
                // ventilation is performed.
                return null;
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * @return array{variant: ProductVariant, referenceExclTax: int}|null
     */
    private function resolveComponent(ProductOptionValue $optionValue): ?array
    {
        $dishZeltyId = $optionValue->getZeltyId();

        if ($dishZeltyId === null) {
            return null;
        }

        $dishProduct = $this->em->getRepository(Product::class)->findOneBy(['code' => $dishZeltyId]);

        if ($dishProduct === null) {
            $this->logger?->warning(sprintf(
                'VAT ventilation: menu component "%s" no longer resolves to a product, falling back to the default rate.',
                $dishZeltyId
            ));

            return null;
        }

        // A dish normally carries a single variant (its à la carte price);
        // see ZeltyProductMapper. Unlike the menu-variant case fixed
        // earlier, there's no evidence of dishes accumulating duplicates —
        // but if that ever happens, whichever variant first() returns still
        // shares the dish's own tax category (Zelty sets tax_rules per dish,
        // not per price point), so only the reference price precision would
        // be affected, never which rate applies.
        $referenceVariant = $dishProduct->getVariants()->first() ?: null;

        if (!$referenceVariant instanceof ProductVariant || $referenceVariant->getTaxCategory() === null) {
            return null;
        }

        $rate = $this->taxRateResolver->resolve($referenceVariant, ['country' => strtolower($this->region)]);

        if ($rate === null) {
            return null;
        }

        $referencePrice = $referenceVariant->getPrice();
        $referenceExclTax = $referencePrice - (int) $this->calculator->calculate($referencePrice, $rate);

        if ($referenceExclTax <= 0) {
            return null;
        }

        return [
            'variant' => $referenceVariant,
            'referenceExclTax' => $referenceExclTax,
        ];
    }

    /**
     * Split $bundleTotal (tax-inclusive) across $components proportionally
     * to their ex-tax reference price, grouping slices that share a tax
     * category into a single entry. The last component absorbs the rounding
     * remainder so the slices always sum back to exactly $bundleTotal.
     *
     * @param array<int, array{variant: ProductVariant, referenceExclTax: int}> $components
     * @return array<int, array{variant: ProductVariant, amount: int}>
     */
    private function allocate(int $bundleTotal, array $components, int $totalReferenceExclTax): array
    {
        $slicesByCategory = [];
        $allocated = 0;
        $lastIndex = count($components) - 1;

        foreach (array_values($components) as $i => $component) {
            $amount = $i === $lastIndex
                ? $bundleTotal - $allocated
                : (int) round($bundleTotal * ($component['referenceExclTax'] / $totalReferenceExclTax));

            $allocated += $amount;

            $categoryCode = $component['variant']->getTaxCategory()->getCode();

            if (!isset($slicesByCategory[$categoryCode])) {
                $slicesByCategory[$categoryCode] = [
                    'variant' => $component['variant'],
                    'amount' => 0,
                ];
            }

            $slicesByCategory[$categoryCode]['amount'] += $amount;
        }

        return array_values($slicesByCategory);
    }
}
