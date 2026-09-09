<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Integration\Zelty\ZeltyMenuVatVentilator;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Taxation\Calculator\DefaultCalculator;

/**
 * The reported bug: a menu bundling a food component (10%) with a beer
 * (20%) got taxed entirely at 20% — ZeltyMenuMapper's "highest component
 * rate" heuristic, which is the BOFiP-correct default *absent* a real
 * ventilation, but over-collects once ventilation is actually possible.
 *
 * This locks in the "menu vs. à la carte" ventilation method from
 * https://bofip.impots.gouv.fr/bofip/256-PGP.html/identifiant%3DBOI-TVA-LIQ-30-20-10-20-20140924#Ventilation_21
 */
class ZeltyMenuVatVentilatorTest extends TestCase
{
    private TaxCategory $reduced; // 10%
    private TaxCategory $standard; // 20%
    private TaxRate $reducedRate;
    private TaxRate $standardRate;

    protected function setUp(): void
    {
        [$this->reduced, $this->reducedRate] = $this->taxCategoryWithRate('BASE_INTERMEDIARY', 0.10);
        [$this->standard, $this->standardRate] = $this->taxCategoryWithRate('BASE_STANDARD', 0.20);
    }

    public function testNonZeltyProductReturnsNull(): void
    {
        $product = new Product();
        $product->setZeltyId(null);

        $variant = $this->menuVariant($product, []);
        $orderItem = $this->orderItem($variant, 1000);

        $ventilator = $this->buildVentilator();

        $this->assertNull($ventilator->ventilate($orderItem));
    }

    public function testZeltyMenuWithASingleComponentReturnsNull(): void
    {
        $burger = $this->dishProduct('ZD_BURGER', $this->reduced, 700);
        $burgerValue = $this->partOptionValue('ZD_BURGER');

        $menuProduct = $this->zeltyMenuProduct('ZM1');
        $variant = $this->menuVariant($menuProduct, [$burgerValue]);
        $orderItem = $this->orderItem($variant, 690);

        $ventilator = $this->buildVentilator([$burger]);

        // Nothing to split: the existing single-category path already
        // handles this correctly.
        $this->assertNull($ventilator->ventilate($orderItem));
    }

    public function testMenuWithFoodAndBeerSplitsProportionallyByALaCartePrice(): void
    {
        // À la carte reference prices, tax-inclusive.
        $burger = $this->dishProduct('ZD_BURGER', $this->reduced, 700); // 7.00€ at 10%
        $fries = $this->dishProduct('ZD_FRIES', $this->reduced, 300); // 3.00€ at 10%
        $beer = $this->dishProduct('ZD_BEER', $this->standard, 500); // 5.00€ at 20%

        $burgerValue = $this->partOptionValue('ZD_BURGER');
        $friesValue = $this->partOptionValue('ZD_FRIES');
        $beerValue = $this->partOptionValue('ZD_BEER');

        $menuProduct = $this->zeltyMenuProduct('ZM1');
        $variant = $this->menuVariant($menuProduct, [$burgerValue, $friesValue, $beerValue]);

        // Sold as a menu for 1200 (cheaper than 700+300+500=1500 à la carte).
        $orderItem = $this->orderItem($variant, 1200);

        $ventilator = $this->buildVentilator([$burger, $fries, $beer]);

        $slices = $ventilator->ventilate($orderItem);

        $this->assertNotNull($slices);
        $this->assertCount(2, $slices); // burger+fries share BASE_INTERMEDIARY, beer is BASE_STANDARD

        // Ex-tax reference prices (DefaultCalculator: base - round(base - base/(1+rate))):
        // burger 700-64=636, fries 300-27=273, beer 500-83=417; total ex-tax = 1326.
        // burger/fries slices are rounded independently; beer (last) absorbs the
        // remainder so the three always sum back to exactly 1200.
        $slicesByCode = [];
        foreach ($slices as $slice) {
            $slicesByCode[$slice['variant']->getTaxCategory()->getCode()] = $slice['amount'];
        }

        $this->assertArrayHasKey('BASE_INTERMEDIARY', $slicesByCode);
        $this->assertArrayHasKey('BASE_STANDARD', $slicesByCode);

        // Slices always sum back exactly to the bundle's actual sold price.
        $this->assertSame(1200, array_sum($slicesByCode));

        // The beer's slice is meaningfully smaller than the food's, and
        // nowhere near "the whole bundle at 20%" (the bug being fixed).
        $this->assertLessThan($slicesByCode['BASE_INTERMEDIARY'], $slicesByCode['BASE_STANDARD']);
        $this->assertLessThan(1200, $slicesByCode['BASE_STANDARD']);

        // Exact expected split given the reference prices above.
        $this->assertEqualsWithDelta(377, $slicesByCode['BASE_STANDARD'], 1);
        $this->assertEqualsWithDelta(823, $slicesByCode['BASE_INTERMEDIARY'], 1);
    }

    public function testUnresolvableDishFallsBackToNull(): void
    {
        $burger = $this->dishProduct('ZD_BURGER', $this->reduced, 700);
        // ZD_BEER is referenced by the order but was never imported/found.
        $burgerValue = $this->partOptionValue('ZD_BURGER');
        $beerValue = $this->partOptionValue('ZD_BEER');

        $menuProduct = $this->zeltyMenuProduct('ZM1');
        $variant = $this->menuVariant($menuProduct, [$burgerValue, $beerValue]);
        $orderItem = $this->orderItem($variant, 1200);

        // Only $burger is registered in the repository; ZD_BEER resolves to null.
        $ventilator = $this->buildVentilator([$burger]);

        $this->assertNull($ventilator->ventilate($orderItem));
    }

    public function testAdditionalSupplementOptionValueIsExcludedFromTheVentilationBase(): void
    {
        $burger = $this->dishProduct('ZD_BURGER', $this->reduced, 700);
        $beer = $this->dishProduct('ZD_BEER', $this->standard, 500);

        $burgerValue = $this->partOptionValue('ZD_BURGER');
        $beerValue = $this->partOptionValue('ZD_BEER');

        // A paid "extra sauce" supplement, additional=true — not one of the
        // menu's fixed components, must not enter the ventilation base.
        $supplementOption = new ProductOption();
        $supplementOption->setAdditional(true);
        $supplementValue = new ProductOptionValue();
        $supplementValue->setOption($supplementOption);
        $supplementValue->setZeltyId('ZD_EXTRA_SAUCE');

        $menuProduct = $this->zeltyMenuProduct('ZM1');
        $variant = $this->menuVariant($menuProduct, [$burgerValue, $beerValue, $supplementValue]);
        $orderItem = $this->orderItem($variant, 1200);

        // No dish registered for ZD_EXTRA_SAUCE at all — if it were
        // (wrongly) considered, resolution would fail and we'd get null.
        $ventilator = $this->buildVentilator([$burger, $beer]);

        $slices = $ventilator->ventilate($orderItem);

        $this->assertNotNull($slices);
        $this->assertCount(2, $slices);
    }

    private function taxCategoryWithRate(string $code, float $amount): array
    {
        $category = new TaxCategory();
        $category->setCode($code);

        $rate = new TaxRate();
        $rate->setAmount($amount);
        $rate->setIncludedInPrice(true);
        $rate->setCategory($category);

        return [$category, $rate];
    }

    private function dishProduct(string $zeltyId, TaxCategory $category, int $price): Product
    {
        $product = new Product();
        $product->setCode($zeltyId);
        $product->setZeltyId($zeltyId);

        $variant = new ProductVariant();
        $variant->setPrice($price);
        $variant->setTaxCategory($category);
        $product->addVariant($variant);

        return $product;
    }

    private function zeltyMenuProduct(string $zeltyId): Product
    {
        $product = new Product();
        $product->setCode($zeltyId);
        $product->setZeltyId($zeltyId);

        return $product;
    }

    private function partOptionValue(string $dishZeltyId): ProductOptionValue
    {
        // Menu parts are always additional=false — see
        // ZeltyMenuMapper::createMenuPartOption().
        $option = new ProductOption();
        $option->setAdditional(false);

        $value = new ProductOptionValue();
        $value->setOption($option);
        $value->setZeltyId($dishZeltyId);

        return $value;
    }

    private function menuVariant(Product $product, array $optionValues): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);

        foreach ($optionValues as $optionValue) {
            $variant->addOptionValue($optionValue);
        }

        return $variant;
    }

    private function orderItem(ProductVariant $variant, int $total): OrderItemInterface
    {
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getTotal')->willReturn($total);

        return $orderItem;
    }

    private function buildVentilator(array $dishProducts = []): ZeltyMenuVatVentilator
    {
        $byCode = [];
        foreach ($dishProducts as $product) {
            $byCode[$product->getCode()] = $product;
        }

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $byCode[$criteria['code']] ?? null
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturnCallback(
            function ($taxable) {
                $category = $taxable->getTaxCategory();
                return match ($category?->getCode()) {
                    'BASE_INTERMEDIARY' => $this->reducedRate,
                    'BASE_STANDARD' => $this->standardRate,
                    default => null,
                };
            }
        );

        return new ZeltyMenuVatVentilator(
            $em,
            $taxRateResolver,
            new DefaultCalculator(),
            'fr',
        );
    }
}
