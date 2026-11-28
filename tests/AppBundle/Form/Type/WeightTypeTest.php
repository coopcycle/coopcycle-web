<?php

declare(strict_types=1);

namespace Tests\AppBundle\Form\Type;

use AppBundle\Form\Type\WeightType;
use Symfony\Component\Form\Test\TypeTestCase;

class WeightTypeTest extends TypeTestCase
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);

        parent::tearDown();
    }

    public function submittedValueProvider(): array
    {
        return [
            ['fr', '4,5', 4500],
            ['fr', '4.5', 4500],
            ['fr', '0,25', 250],
            ['fr', '12', 12000],
            ['en', '4.5', 4500],
            ['en', '4,5', 4500],
            ['fr', '', null],
        ];
    }

    /**
     * @dataProvider submittedValueProvider
     */
    public function testSubmitConvertsKilogramsToGrams(string $locale, string $submitted, ?int $expected): void
    {
        \Locale::setDefault($locale);

        $form = $this->factory->create(WeightType::class);
        $form->submit($submitted);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame($expected, $form->getData());
    }

    public function testSubmitRejectsText(): void
    {
        \Locale::setDefault('fr');

        $form = $this->factory->create(WeightType::class);
        $form->submit('heavy');

        $this->assertFalse($form->isSynchronized());
    }

    /**
     * The displayed separator depends on the ICU data available
     * (the Alpine image only ships English), but what is displayed must be accepted back.
     */
    public function testDisplayedValueIsAcceptedBack(): void
    {
        foreach (['fr', 'en'] as $locale) {
            \Locale::setDefault($locale);

            $displayed = $this->factory->create(WeightType::class, 4500)->createView()->vars['value'];

            $form = $this->factory->create(WeightType::class, 4500);
            $form->submit($displayed);

            $this->assertSame(4500, $form->getData(), sprintf('Locale "%s", displayed "%s"', $locale, $displayed));
        }
    }

    public function testRendersATextInputWithDecimalKeypad(): void
    {
        $view = $this->factory->create(WeightType::class)->createView();

        // No "type" var means form_widget_simple renders type="text"
        $this->assertArrayNotHasKey('type', $view->vars);
        $this->assertSame('decimal', $view->vars['attr']['inputmode']);
    }
}
