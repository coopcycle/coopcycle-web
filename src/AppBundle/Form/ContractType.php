<?php

namespace AppBundle\Form;

use AppBundle\Entity\Contract;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('minimumCartAmount', MoneyType::class, [
                'label' => 'restaurant.contract.minimumCartAmount.label',
                'divisor' => 100,
            ])
            ->add('flatDeliveryPrice', MoneyType::class, [
                'label' => 'restaurant.contract.flatDeliveryPrice.label',
                'divisor' => 100,
            ])
            ->add('customerAmount', MoneyType::class, [
                'label' => 'restaurant.contract.customerAmount.label',
                'divisor' => 100,
            ])
            ->add('feeRate', PercentType::class, [
                'label' => 'restaurant.contract.feeRate.label',
                'scale' => 2,
                'type' => 'fractional',
            ])
            ->add('restaurantPaysStripeFee', ChoiceType::class, [
                'label' => 'restaurant.contract.restaurantPaysStripeFee.label',
                'choices' => array(
                    'restaurant.contract.restaurantPaysStripeFee.restaurant' => true,
                    'restaurant.contract.restaurantPaysStripeFee.cooperative' => false
                )
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Contract::class
        ]);
    }

}
