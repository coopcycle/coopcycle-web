<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\ExpressionLanguage\PackagesResolver;
use AppBundle\ExpressionLanguage\PriceEvaluation;
use AppBundle\ExpressionLanguage\PriceRangeExpressionLanguageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PriceRangeExpressionLanguageProviderTest extends TestCase
{
    private $language;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();

        $this->language->registerProvider(new PriceRangeExpressionLanguageProvider());
    }

    public function returnValueProviderForDistance()
    {
        return [
            [  1500,    0 ],
            [  3000,  new PriceEvaluation(450, 1) ],
            [  4000,  new PriceEvaluation(450, 1) ],
            [  5500,  new PriceEvaluation(450, 2) ],
            [  6500,  new PriceEvaluation(450, 2) ],
            [ 12000,  new PriceEvaluation(450, 5) ],
            [  null,    0 ],
        ];
    }

    /**
     * @dataProvider returnValueProviderForDistance
     */
    public function testReturnValueWithDistance($distance, $expectedValue)
    {
        $value = $this->language->evaluate('price_range(distance, 450, 2000, 2500)', [
            'distance' => $distance,
        ]);

        $this->assertEquals($expectedValue, $value);
    }

    public function returnValueProviderForTotalVolumeUnits()
    {
        return [
            [  2.0, 1, new PriceEvaluation(100, 2) ],
            [  3.0, 2, new PriceEvaluation(100, 6) ],
        ];
    }

    /**
     * @dataProvider returnValueProviderForTotalVolumeUnits
     */
    public function testReturnValueWithTotalVolumeUnits($volumeUnits, $quantity, $expectedValue)
    {
        $delivery = new Delivery();

        $package = new Package();
        $package->setMaxVolumeUnits($volumeUnits);

        $delivery->addPackageWithQuantity($package, $quantity);

        $value = $this->language->evaluate('price_range(packages.totalVolumeUnits(), 100, 1, 0)', [
            'packages' => new PackagesResolver($delivery),
        ]);

        $this->assertEquals($expectedValue, $value);
    }

    public function returnValueProviderForWeight()
    {
        return [
            [  35000,      0 ],
            [  45000,    new PriceEvaluation(360, 1) ],
            [  80000,    new PriceEvaluation(360, 1) ],
            [  81000,    new PriceEvaluation(360, 2) ],
            [   null,      0 ]
        ];
    }

    /**
     * @dataProvider returnValueProviderForWeight
     */
    public function testReturnValueWithWeight($weight, $expectedValue)
    {
        $value = $this->language->evaluate('price_range(weight, 360, 40000, 40000)', [
            'weight' => $weight,
        ]);

        $this->assertEquals($expectedValue, $value);
    }

    public function returnValueProviderForQuantity()
    {
        return [
            [  1, new PriceEvaluation(500, 1) ],
            [  2, new PriceEvaluation(500, 2) ],
            [  3, new PriceEvaluation(500, 3) ],
            [  4, new PriceEvaluation(500, 4) ],
            [ null,  0 ]
        ];
    }

    /**
     * @dataProvider returnValueProviderForQuantity
     */
    public function testReturnValueWithQuantity($quantity, $expectedValue)
    {
        $value = $this->language->evaluate('price_range(quantity, 500, 1, 0)', [
            'quantity' => $quantity,
        ]);

        $this->assertEquals($expectedValue, $value);
    }

    public function testValueBelowThreshold()
    {
        $value = $this->language->evaluate('price_range(distance, 24, 100, 2000)', [
            'distance' => 850,
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals(0, $value);
    }
}
