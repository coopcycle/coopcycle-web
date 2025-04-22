<?php

namespace AppBundle\ExpressionLanguage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PricePercentageExpressionLanguageProviderTest extends TestCase
{
    private $language;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();
    }

    public function returnValueProvider()
    {
        return [
            ['price_percentage(10000)', 10000],
        ];
    }

    /**
     * @dataProvider returnValueProvider
     */
    public function testReturnValue($expression, $expectedValue)
    {
        $this->language->registerProvider(new PricePercentageExpressionLanguageProvider());

        $value = $this->language->evaluate($expression, [
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }
}
