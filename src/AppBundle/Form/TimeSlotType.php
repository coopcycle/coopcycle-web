<?php

namespace AppBundle\Form;

use AppBundle\Entity\TimeSlot;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeSlotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
            	'label' => 'form.time_slot.name.label'
            ])
            ->add('interval', ChoiceType::class, [
                'choices'  => [
                    '2 days' => '2 days',
                    '3 days' => '3 days',
                    '1 week' => '1 week',
                ],
            ])
            ->add('workingDaysOnly', CheckboxType::class, [
                'label' => 'form.time_slot.working_days_only.label',
                'required' => false,
            ])
            ->add('choices', CollectionType::class, [
                'entry_type' => TimeSlotChoiceType::class,
                'entry_options' => ['label' => false],
                'label' => 'form.time_slot.choices.label',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [
                    'error_bubbling' => false
                ],
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => false,
                'error_bubbling' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TimeSlot::class,
        ));
    }
}
