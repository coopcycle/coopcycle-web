<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\ExpressionLanguage\PackagesResolver;
use AppBundle\ExpressionLanguage\PricePerPackageExpressionLanguageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PricePerPackageExpressionLanguageProviderTest extends TestCase
{
    private $language;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();
    }

    public function returnValueProvider()
    {
        return [
            [  1,    1240 ],
            [  2,    2480 ],
            [  3,    2900 ],
            [  4,    3320 ],
        ];
    }

    /**
     * @dataProvider returnValueProvider
     */
    public function testReturnValue($quantity, $expectedValue)
    {
        $this->language->registerProvider(new PricePerPackageExpressionLanguageProvider());

        $delivery = new Delivery();
        $package = new Package();
        $package->setName('XXL');

        $delivery->addPackageWithQuantity($package, $quantity);

        $value = $this->language->evaluate('price_per_package(packages, "XXL", 1240, 2, 420)', [
            'packages' => new PackagesResolver($delivery),
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }
}
