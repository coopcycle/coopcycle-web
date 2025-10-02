<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\ExpressionLanguage\PriceEvaluation;
use AppBundle\Pricing\OnDemandDeliveryProductProcessor;
use AppBundle\Pricing\PriceExpressionParser;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
use AppBundle\Pricing\ProductOptionValueWithQuantity;
use AppBundle\Pricing\RuleHumanizer;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class OnDemandDeliveryProductProcessorTest extends TestCase
{
    private ProductOptionValueFactory $productOptionValueFactory;
    private RuleHumanizer $ruleHumanizer;
    private ExpressionLanguage $expressionLanguage;
    private PriceExpressionParser $priceExpressionParser;
    private LoggerInterface $logger;
    private OnDemandDeliveryProductProcessor $processor;

    protected function setUp(): void
    {
        $this->productOptionValueFactory = $this->createMock(ProductOptionValueFactory::class);
        $this->ruleHumanizer = $this->createMock(RuleHumanizer::class);
        $this->expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $this->priceExpressionParser = $this->createMock(PriceExpressionParser::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new OnDemandDeliveryProductProcessor(
            $this->productOptionValueFactory,
            $this->ruleHumanizer,
            $this->expressionLanguage,
            $this->priceExpressionParser,
            $this->logger
        );
    }

    public function testProcessPricingRuleWithArrayResultMismatch(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn([new PriceEvaluation(1240, 1), new PriceEvaluation(420, 1)]); // Array with 2 elements

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRuleWithNonArrayResultMismatch(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue1 = $this->createMock(ProductOptionValue::class);
        $productOptionValue2 = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue1, $productOptionValue2]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn(100); // Non-array result

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRuleFixedPriceWithArrayResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn([new PriceEvaluation(1240, 1)]);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRuleFixedPriceWithPriceEvaluationResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn(new PriceEvaluation(100, 2));

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRuleFixedPriceWithIntResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn(100);

        $productOptionValue->method('getPrice')->willReturn(100);
        $productOptionValue->expects($this->never())->method('setPrice');

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(1, $result[0]->quantity);
    }

    public function testProcessPricingRuleFixedPriceWithIntResultPriceUpdate(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('100');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new FixedPriceExpression(100));

        $rule->method('apply')->willReturn(150);

        $productOptionValue->method('getPrice')->willReturn(100);
        $productOptionValue->expects($this->once())->method('setPrice')->with(150);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(1, $result[0]->quantity);
    }

    public function testProcessPricingRulePercentageWithArrayResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('percentage(10500)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePercentageExpression(10500));

        $rule->method('apply')->willReturn([new PriceEvaluation(1240, 1)]);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePercentageWithPriceEvaluationResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('percentage(10500)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePercentageExpression(10500));

        $rule->method('apply')->willReturn(new PriceEvaluation(100, 2));

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePercentageWithIntResultPositive(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('percentage(10500)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePercentageExpression(10500));

        $rule->method('apply')->willReturn(10500);

        $productOptionValue->method('getPrice')->willReturn(1);
        $productOptionValue->expects($this->never())->method('setPrice');

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(10500, $result[0]->quantity);
    }

    public function testProcessPricingRulePercentageWithIntResultPriceUpdate(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('percentage(9500)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePercentageExpression(9500));

        $rule->method('apply')->willReturn(9500);

        $productOptionValue->method('getPrice')->willReturn(2); // Wrong price
        $productOptionValue->expects($this->once())->method('setPrice')->with(-1);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(9500, $result[0]->quantity);
    }

    public function testProcessPricingRulePriceRangeWithArrayResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_range(distance, 100, 50, 1000)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PriceRangeExpression('distance', 100, 50, 1000));

        $rule->method('apply')->willReturn([new PriceEvaluation(100, 2)]);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePriceRangeWithPriceEvaluationResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_range(distance, 100, 50, 1000)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PriceRangeExpression('distance', 100, 50, 1000));

        $rule->method('apply')->willReturn(new PriceEvaluation(100, 3));

        $productOptionValue->method('getPrice')->willReturn(100);
        $productOptionValue->expects($this->never())->method('setPrice');

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(3, $result[0]->quantity);
    }

    public function testProcessPricingRulePriceRangeWithPriceEvaluationResultPriceUpdate(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_range(distance, 100, 50, 1000)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PriceRangeExpression('distance', 100, 50, 1000));

        $productOptionValue->method('getPrice')->willReturn(100); // Different from expected
        $productOptionValue->expects($this->once())->method('setPrice')->with(150);

        $rule->method('apply')->willReturn(new PriceEvaluation(150, 2));

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(2, $result[0]->quantity);
    }

    public function testProcessPricingRulePriceRangeWithZeroIntResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_range(distance, 100, 50, 1000)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PriceRangeExpression('distance', 100, 50, 1000));

        $rule->method('apply')->willReturn(0); // Rule doesn't apply

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePriceRangeWithNonZeroIntResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_range(distance, 100, 50, 1000)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PriceRangeExpression('distance', 100, 50, 1000));

        $rule->method('apply')->willReturn(50); // Non-valid result

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePricePerPackageWithArrayResultMatching(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue1 = $this->createMock(ProductOptionValue::class);
        $productOptionValue2 = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue1, $productOptionValue2]));

        $rule->method('getPrice')->willReturn('price_per_package(small, 100, 2, 200)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePerPackageExpression('small', 100, 2, 200));

        $productOptionValue1->method('getPrice')->willReturn(100);
        $productOptionValue1->expects($this->never())->method('setPrice');
        $productOptionValue2->method('getPrice')->willReturn(200);
        $productOptionValue2->expects($this->never())->method('setPrice');

        $rule->method('apply')->willReturn([
            new PriceEvaluation(100, 2),
            new PriceEvaluation(200, 1)
        ]);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[1]);
        $this->assertSame($productOptionValue1, $result[0]->productOptionValue);
        $this->assertSame(2, $result[0]->quantity);
        $this->assertSame($productOptionValue2, $result[1]->productOptionValue);
        $this->assertSame(1, $result[1]->quantity);
    }

    public function testProcessPricingRulePricePerPackageWithArrayResultNoMatch(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_per_package(small, 100, 0, 0)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePerPackageExpression('small', 100, 0, 0));

        $productOptionValue->method('getPrice')->willReturn(100); // Different from 300
        $productOptionValue->expects($this->once())->method('setPrice')->with(300);

        $rule->method('apply')->willReturn([
            new PriceEvaluation(300, 1) // No matching product option value
        ]);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(1, $result[0]->quantity);
    }

    public function testProcessPricingRulePricePerPackageWithPriceEvaluationResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_per_package(small, 100, 0, 0)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePerPackageExpression('small', 100, 0, 0));

        $productOptionValue->method('getPrice')->willReturn(100);

        $rule->method('apply')->willReturn(new PriceEvaluation(100, 3));

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductOptionValueWithQuantity::class, $result[0]);
        $this->assertSame($productOptionValue, $result[0]->productOptionValue);
        $this->assertSame(3, $result[0]->quantity);
    }

    public function testProcessPricingRulePricePerPackageWithZeroIntResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_per_package(small, 100, 0, 0)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePerPackageExpression('small', 100, 0, 0));

        $rule->method('apply')->willReturn(0); // Rule doesn't apply

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRulePricePerPackageWithNonZeroIntResult(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('price_per_package(small, 100, 0, 0)');
        $this->priceExpressionParser->method('parsePrice')->willReturn(new PricePerPackageExpression('small', 100, 0, 0));

        $rule->method('apply')->willReturn(50); // Non-valid result

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessPricingRuleWithUnsupportedExpression(): void
    {
        $rule = $this->createMock(PricingRule::class);
        $productOptionValue = $this->createMock(ProductOptionValue::class);
        $priceExpression = $this->createMock(PriceExpression::class); // Unknown expression type

        $rule->method('getProductOptionValues')->willReturn(new ArrayCollection([$productOptionValue]));

        $rule->method('getPrice')->willReturn('unknown_expression(100)');
        $this->priceExpressionParser->method('parsePrice')->willReturn($priceExpression);

        $rule->method('apply')->willReturn(100);

        $result = $this->processor->processPricingRule($rule, []);

        $this->assertEmpty($result);
    }

    public function testProcessWithTaskVariantsOnly(): void
    {
        $taskVariant1 = $this->createMock(ProductVariantInterface::class);
        $taskVariant2 = $this->createMock(ProductVariantInterface::class);
        $optionValue = $this->createMock(ProductOptionValueInterface::class);

        $taskVariant1->method('getOptionValues')->willReturn(new ArrayCollection([$optionValue]));
        $taskVariant1->method('getQuantityForOptionValue')->willReturn(2);
        $taskVariant1->method('getOptionValuesPrice')->willReturn(200);
        $taskVariant1->expects($this->once())->method('setPrice')->with(0);

        $taskVariant2->method('getOptionValues')->willReturn(new ArrayCollection([$optionValue]));
        $taskVariant2->method('getQuantityForOptionValue')->willReturn(1);
        $taskVariant2->method('getOptionValuesPrice')->willReturn(100);
        $taskVariant2->expects($this->once())->method('setPrice')->with(0);

        $optionValue->method('getOptionCode')->willReturn(ProductOptionRepository::PRODUCT_OPTION_CODE_FIXED_PRICE);
        $optionValue->method('getPrice')->willReturn(100);

        $result = $this->processor->process([$taskVariant1, $taskVariant2], null);

        $this->assertCount(2, $result);
        $this->assertSame($taskVariant1, $result[0]);
        $this->assertSame($taskVariant2, $result[1]);
    }

    public function testProcessWithTaskVariantsAndDeliveryVariant(): void
    {
        $taskVariant = $this->createMock(ProductVariantInterface::class);
        $deliveryVariant = $this->createMock(ProductVariantInterface::class);
        $optionValue = $this->createMock(ProductOptionValueInterface::class);

        $taskVariant->method('getOptionValues')->willReturn(new ArrayCollection([$optionValue]));
        $taskVariant->method('getQuantityForOptionValue')->willReturn(1);
        $taskVariant->method('getOptionValuesPrice')->willReturn(100);
        $taskVariant->expects($this->once())->method('setPrice')->with(0);

        $deliveryVariant->method('getOptionValues')->willReturn(new ArrayCollection([$optionValue]));
        $deliveryVariant->method('getQuantityForOptionValue')->willReturn(1);
        $deliveryVariant->expects($this->once())->method('setPrice')->with(0);

        $optionValue->method('getOptionCode')->willReturn(ProductOptionRepository::PRODUCT_OPTION_CODE_FIXED_PRICE);
        $optionValue->method('getPrice')->willReturn(100);

        $result = $this->processor->process([$taskVariant], $deliveryVariant);

        $this->assertCount(2, $result);
        $this->assertSame($taskVariant, $result[0]);
        $this->assertSame($deliveryVariant, $result[1]);
    }

    public function testProcessWithEmptyTaskVariants(): void
    {
        $deliveryVariant = $this->createMock(ProductVariantInterface::class);
        $optionValue = $this->createMock(ProductOptionValueInterface::class);

        $deliveryVariant->method('getOptionValues')->willReturn(new ArrayCollection([$optionValue]));
        $deliveryVariant->method('getQuantityForOptionValue')->willReturn(1);
        $deliveryVariant->expects($this->once())->method('setPrice')->with(0);

        $optionValue->method('getOptionCode')->willReturn(ProductOptionRepository::PRODUCT_OPTION_CODE_FIXED_PRICE);
        $optionValue->method('getPrice')->willReturn(100);

        $result = $this->processor->process([], $deliveryVariant);

        $this->assertCount(1, $result);
        $this->assertSame($deliveryVariant, $result[0]);
    }

    public function testProcessWithTaskVariantsAndDeliveryVariantPercentage(): void
    {
        $taskVariant = $this->createMock(ProductVariantInterface::class);
        $deliveryVariant = $this->createMock(ProductVariantInterface::class);
        $regularOptionValue = $this->createMock(ProductOptionValueInterface::class);
        $percentageOptionValue = $this->createMock(ProductOptionValueInterface::class);

        // Task variant with regular option
        $taskVariant->method('getOptionValues')->willReturn(new ArrayCollection([$regularOptionValue]));
        $taskVariant->method('getQuantityForOptionValue')->willReturn(1);
        $taskVariant->method('getOptionValuesPrice')->willReturn(100);
        $taskVariant->expects($this->once())->method('setPrice')->with(0);

        // Delivery variant with percentage option
        $deliveryVariant->method('getOptionValues')->willReturn(new ArrayCollection([$percentageOptionValue]));
        $deliveryVariant->method('getQuantityForOptionValue')->willReturn(10500); // 105%
        $deliveryVariant->expects($this->once())->method('addOptionValueWithQuantity')->with($percentageOptionValue, 5);
        $deliveryVariant->expects($this->once())->method('setPrice')->with(0);

        $regularOptionValue->method('getOptionCode')->willReturn(ProductOptionRepository::PRODUCT_OPTION_CODE_FIXED_PRICE);
        $regularOptionValue->method('getPrice')->willReturn(100);

        $percentageOptionValue->method('getOptionCode')->willReturn(ProductOptionRepository::PRODUCT_OPTION_CODE_PRICE_PERCENTAGE);

        $result = $this->processor->process([$taskVariant], $deliveryVariant);

        $this->assertCount(2, $result);
        $this->assertSame($taskVariant, $result[0]);
        $this->assertSame($deliveryVariant, $result[1]);
    }
}
