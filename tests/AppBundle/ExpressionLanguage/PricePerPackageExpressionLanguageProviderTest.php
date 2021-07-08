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
            [ 'price_per_package(packages, "XXL", 1240, 2, 420)', 1, 1240 ],
            [ 'price_per_package(packages, "XXL", 1240, 2, 420)', 2, 1660 ],
            [ 'price_per_package(packages, "XXL", 1240, 2, 420)', 3, 2080 ],
            [ 'price_per_package(packages, "XXL", 1240, 2, 420)', 4, 2500 ],
            [ 'price_per_package(packages, "XXL", 1240, 0, 420)', 4, 4960 ],
        ];
    }

    /**
     * @dataProvider returnValueProvider
     */
    public function testReturnValue($expression, $quantity, $expectedValue)
    {
        $this->language->registerProvider(new PricePerPackageExpressionLanguageProvider());

        $delivery = new Delivery();
        $package = new Package();
        $package->setName('XXL');

        $delivery->addPackageWithQuantity($package, $quantity);

        $value = $this->language->evaluate($expression, [
            'packages' => new PackagesResolver($delivery),
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }
}
