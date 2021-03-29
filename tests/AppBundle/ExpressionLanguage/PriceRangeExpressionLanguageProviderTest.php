<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\PriceRangeExpressionLanguageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PriceRangeExpressionLanguageProviderTest extends TestCase
{
    private $language;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();
    }

    public function returnValueProvider()
    {
        return [
            [  1500,    0 ],
            [  3000,  450 ],
            [  4000,  450 ],
            [  5500,  900 ],
            [  6500,  900 ],
            [ 12000, 2250 ],
        ];
    }

    /**
     * @dataProvider returnValueProvider
     */
    public function testReturnValue($distance, $expectedValue)
    {
        $this->language->registerProvider(new PriceRangeExpressionLanguageProvider());

        $value = $this->language->evaluate(sprintf('price_range(distance, 450, 2000, 2500)', ), [
            'distance' => $distance,
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }
}
