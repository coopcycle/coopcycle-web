<?php

namespace AppBundle\Form\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin-editable fields for one tier. Name/position are fixed identifiers
 * seeded by the migration, not exposed here.
 */
class ReferralLevelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minReferralCount', IntegerType::class, [
                'label' => 'referral.level.field.minReferralCount',
                'help' => 'referral.level.field.minReferralCount.help',
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('rewardType', ChoiceType::class, [
                'label' => 'referral.level.field.rewardType',
                'help' => 'referral.level.field.rewardType.help',
                'choices' => [
                    'referral.reward_type.fixed' => FixedDiscountPromotionActionCommand::TYPE,
                    'referral.reward_type.percentage' => PercentageDiscountPromotionActionCommand::TYPE,
                ],
            ])
            ->add('rewardAmount', MoneyType::class, [
                'label' => 'referral.level.field.rewardAmount',
                'help' => 'referral.level.field.rewardAmount.help',
                'required' => false,
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('rewardPercentage', NumberType::class, [
                'label' => 'referral.level.field.rewardPercentage',
                'help' => 'referral.level.field.rewardPercentage.help',
                'required' => false,
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('couponValidityDays', IntegerType::class, [
                'label' => 'referral.level.field.couponValidityDays',
                'help' => 'referral.level.field.couponValidityDays.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('usageLimit', IntegerType::class, [
                'label' => 'referral.level.field.usageLimit',
                'help' => 'referral.level.field.usageLimit.help',
                'required' => false,
                'constraints' => [new Assert\Positive()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReferralLevel::class,
        ]);
    }
}
