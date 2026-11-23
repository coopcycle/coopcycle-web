<?php

declare(strict_types=1);

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The weight is entered in kilograms, but stored in grams
 */
class WeightType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn (?int $grams): ?float => is_null($grams) ? null : $grams / 1000,
            fn (int|float|null $kilograms): ?int => is_null($kilograms) ? null : (int) round($kilograms * 1000),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Not using a HTML5 number input, as browsers reject "4,5" depending on their locale.
        // As grouping is disabled, NumberType accepts both "4,5" & "4.5", whatever the locale.
        $resolver->setDefaults([
            'required' => false,
            'html5' => false,
            'attr' => [
                'inputmode' => 'decimal',
            ],
        ]);
    }

    public function getParent(): string
    {
        return NumberType::class;
    }
}
