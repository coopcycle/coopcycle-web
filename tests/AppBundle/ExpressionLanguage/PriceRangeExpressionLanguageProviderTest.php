<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\ExpressionLanguage\PackagesResolver;
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
            [  3000,  450 ],
            [  4000,  450 ],
            [  5500,  900 ],
            [  6500,  900 ],
            [ 12000, 2250 ],
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

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }

    public function returnValueProviderForTotalVolumeUnits()
    {
        return [
            [  2.0, 1, 200 ],
            [  3.0, 2, 600 ],
        ];
    }

    /**
     * @dataProvider returnValueProviderForTotalVolumeUnits
     */
    public function testReturnValueWithTotalVolumeUnits($volumeUnits, $quantity, $expectedValue)
    {
        $delivery = new Delivery();

        $package = new Package();
        $package->setVolumeUnits($volumeUnits);

        $delivery->addPackageWithQuantity($package, $quantity);

        $value = $this->language->evaluate('price_range(packages.totalVolumeUnits(), 100, 1, 0)', [
            'packages' => new PackagesResolver($delivery),
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }

    public function returnValueProviderForWeight()
    {
        return [
            [  35000,      0 ],
            [  45000,    360 ],
            [  80000,    360 ],
            [  81000,    720 ],
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

        $this->assertThat($value, $this->isType('int'));
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
