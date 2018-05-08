<?php

namespace AppBundle\Form;

use AppBundle\Entity\Contract;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('minimumCartAmount', MoneyType::class, [
                'label' => 'restaurant.contract.minimumCartAmount.label'
            ])
            ->add('flatDeliveryPrice', MoneyType::class, [
                'label' => 'restaurant.contract.flatDeliveryPrice.label'
            ])
            ->add('feeRate', PercentType::class, [
                'label' => 'restaurant.contract.feeRate.label'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Contract::class
        ]);
    }

}
