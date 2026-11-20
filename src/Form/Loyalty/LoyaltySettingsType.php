<?php

namespace AppBundle\Form\Loyalty;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * How points are earned. Plain scalars stored through Craue/SettingsManager,
 * same pattern as ReferralWelcomeSettingsType.
 */
class LoyaltySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loyalty_points_per_currency_unit', IntegerType::class, [
                'label' => 'loyalty.settings.field.pointsPerCurrencyUnit',
                'help' => 'loyalty.settings.field.pointsPerCurrencyUnit.help',
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('loyalty_points_validity_days', IntegerType::class, [
                'label' => 'loyalty.settings.field.pointsValidityDays',
                'help' => 'loyalty.settings.field.pointsValidityDays.help',
                'constraints' => [new Assert\PositiveOrZero()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
