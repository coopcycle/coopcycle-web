<?php

namespace AppBundle\Form\GiftCard;

use AppBundle\Form\Type\MoneyType;
use AppBundle\Service\GiftCard\GiftCardStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * How gift cards behave on this instance. Plain scalars stored through
 * Craue/SettingsManager, same pattern as LoyaltySettingsType.
 */
class GiftCardSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // MoneyType, not IntegerType: these are stored in cents like
            // every other amount in the codebase, and its divisor of 100
            // keeps that out of the admin's face -- they set 10,00 €, not
            // 1000.
            ->add('gift_card_min_amount', MoneyType::class, [
                'label' => 'gift_card.settings.field.minAmount',
                'help' => 'gift_card.settings.field.minAmount.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('gift_card_max_amount', MoneyType::class, [
                'label' => 'gift_card.settings.field.maxAmount',
                'help' => 'gift_card.settings.field.maxAmount.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('gift_card_denominations', TextType::class, [
                'label' => 'gift_card.settings.field.denominations',
                'help' => 'gift_card.settings.field.denominations.help',
                'required' => false,
            ])
            ->add('gift_card_expiry_policy', ChoiceType::class, [
                'label' => 'gift_card.settings.field.expiryPolicy',
                'help' => 'gift_card.settings.field.expiryPolicy.help',
                'choices' => [
                    'gift_card.settings.expiryPolicy.never' => GiftCardStatus::EXPIRY_POLICY_NEVER,
                    'gift_card.settings.expiryPolicy.forfeit' => GiftCardStatus::EXPIRY_POLICY_FORFEIT,
                ],
            ])
            ->add('gift_card_validity_months', IntegerType::class, [
                'label' => 'gift_card.settings.field.validityMonths',
                'help' => 'gift_card.settings.field.validityMonths.help',
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            // Not a decoration: a voucher sale is outside the scope of VAT,
            // and the receipt has to say so. What it has to say differs by
            // country, and most CoopCycle instances aren't in France -- or
            // in the EU at all -- so this cannot be hardcoded.
            ->add('gift_card_legal_mention', TextareaType::class, [
                'label' => 'gift_card.settings.field.legalMention',
                'help' => 'gift_card.settings.field.legalMention.help',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
