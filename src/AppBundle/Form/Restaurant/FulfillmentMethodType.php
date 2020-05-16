<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FulfillmentMethodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [
                    'error_bubbling' => false
                ],
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => 'localBusiness.form.openingHours',
                'error_bubbling' => false
            ])
            ->add('openingHoursBehavior', ChoiceType::class, [
                'label' => 'localBusiness.form.openingHoursBehavior',
                'choices'  => [
                    'localBusiness.form.openingHoursBehavior.asap' => 'asap',
                    'localBusiness.form.openingHoursBehavior.time_slot' => 'time_slot',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => FulfillmentMethod::class,
        ));
    }
}

