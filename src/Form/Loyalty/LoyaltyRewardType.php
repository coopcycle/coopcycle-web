<?php

namespace AppBundle\Form\Loyalty;

use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One entry in the catalogue customers spend their points on.
 */
class LoyaltyRewardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'loyalty.reward.field.name',
                'help' => 'loyalty.reward.field.name.help',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('pointsCost', IntegerType::class, [
                'label' => 'loyalty.reward.field.pointsCost',
                'help' => 'loyalty.reward.field.pointsCost.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('rewardType', ChoiceType::class, [
                'label' => 'loyalty.reward.field.rewardType',
                'choices' => [
                    'referral.reward_type.free_delivery' => DeliveryPercentageDiscountPromotionActionCommand::TYPE,
                    'referral.reward_type.fixed' => FixedDiscountPromotionActionCommand::TYPE,
                    'referral.reward_type.percentage' => PercentageDiscountPromotionActionCommand::TYPE,
                ],
            ])
            ->add('rewardAmount', MoneyType::class, [
                'label' => 'loyalty.reward.field.rewardAmount',
                'help' => 'loyalty.reward.field.rewardAmount.help',
                'required' => false,
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('rewardPercentage', NumberType::class, [
                'label' => 'loyalty.reward.field.rewardPercentage',
                'help' => 'loyalty.reward.field.rewardPercentage.help',
                'required' => false,
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('couponValidityDays', IntegerType::class, [
                'label' => 'loyalty.reward.field.couponValidityDays',
                'help' => 'loyalty.reward.field.couponValidityDays.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'loyalty.reward.field.enabled',
                'help' => 'loyalty.reward.field.enabled.help',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoyaltyReward::class,
            'constraints' => [new Assert\Callback([$this, 'validateRewardConfiguration'])],
        ]);
    }

    /**
     * Same rationale as the referral forms: Fixed/PercentageDiscountPromotionActionCommand
     * both gate on isset($configuration[...]), which treats a null amount as
     * "no value" rather than an error -- so an incompletely configured reward
     * would cost a customer their points for a coupon worth nothing.
     */
    public function validateRewardConfiguration(LoyaltyReward $reward, ExecutionContextInterface $context): void
    {
        if (FixedDiscountPromotionActionCommand::TYPE === $reward->getRewardType() && null === $reward->getRewardAmount()) {
            $context->buildViolation('loyalty.reward.field.rewardAmount.required_for_fixed')
                ->setTranslationDomain('messages')
                ->atPath('rewardAmount')
                ->addViolation();
        }

        if (PercentageDiscountPromotionActionCommand::TYPE === $reward->getRewardType() && null === $reward->getRewardPercentage()) {
            $context->buildViolation('loyalty.reward.field.rewardPercentage.required_for_percentage')
                ->setTranslationDomain('messages')
                ->atPath('rewardPercentage')
                ->addViolation();
        }
    }
}
