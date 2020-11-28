<?php

namespace AppBundle\Form;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Form\Type\MoneyType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('flatDeliveryPrice', MoneyType::class, [
                'label' => 'restaurant.contract.flatDeliveryPrice.label',
                'help' => 'restaurant.contract.flatDeliveryPrice.help',
            ])
            ->add('variableDeliveryPriceEnabled', ChoiceType::class, array(
                'label' => 'restaurant.contract.variableDeliveryPriceEnabled.label',
                'choices' => [
                    'basics.no' => false,
                    'basics.yes' => true,
                ],
                'expanded' => true,
                'multiple' => false,
            ))
            ->add('variableDeliveryPrice', EntityType::class, array(
                'required' => false,
                'placeholder' => 'restaurant.contract.variableDeliveryPrice.placeholder',
                'label' => 'restaurant.contract.variableDeliveryPrice.label',
                'class' => PricingRuleSet::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                }
            ))
            ->add('variableCustomerAmountEnabled', ChoiceType::class, array(
                'label' => 'restaurant.contract.variableCustomerAmountEnabled.label',
                'choices' => [
                    'basics.no' => false,
                    'basics.yes' => true,
                ],
                'expanded' => true,
                'multiple' => false,
            ))
            ->add('customerAmount', MoneyType::class, [
                'label' => 'restaurant.contract.customerAmount.label',
                'help' => 'restaurant.contract.customerAmount.help',
            ])
            ->add('variableCustomerAmount', EntityType::class, array(
                'required' => false,
                'placeholder' => 'restaurant.contract.variableCustomerAmount.placeholder',
                'label' => 'restaurant.contract.variableCustomerAmount.label',
                'class' => PricingRuleSet::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                }
            ))
            ->add('feeRate', PercentType::class, [
                'label' => 'restaurant.contract.feeRate.label',
                'help' => 'restaurant.contract.feeRate.help',
                'scale' => 2,
                'type' => 'fractional',
            ])
            ->add('takeAwayFeeRate', PercentType::class, [
                'label' => 'restaurant.contract.takeAwayFeeRate.label',
                'help' => 'restaurant.contract.takeAwayFeeRate.help',
                'scale' => 2,
                'type' => 'fractional',
            ]);

        if ($options['with_advanced_options']) {
            $builder
                ->add('restaurantPaysStripeFee', ChoiceType::class, [
                    'label' => 'restaurant.contract.restaurantPaysStripeFee.label',
                    'choices' => array(
                        'restaurant.contract.restaurantPaysStripeFee.restaurant' => true,
                        'restaurant.contract.restaurantPaysStripeFee.cooperative' => false
                    )
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Contract::class,
            'with_advanced_options' => true,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'contract';
    }
}
