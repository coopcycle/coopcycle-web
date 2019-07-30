<?php

namespace AppBundle\Form;

use AppBundle\Entity\TimeSlot\Choice as TimeSlotChoice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeSlotChoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('startTime', TimeType::class, [
            	'label' => 'form.time_slot_choice.start_time.label',
                'input' => 'string'
            ])
            ->add('endTime', TimeType::class, [
            	'label' => 'form.time_slot_choice.end_time.label',
                'input' => 'string'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TimeSlotChoice::class,
        ));
    }
}
