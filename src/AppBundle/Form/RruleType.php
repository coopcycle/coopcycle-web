<?php

namespace AppBundle\Form;

use AppBundle\Entity\Rrule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RruleType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('rule', ChoiceType::class, [
                'choices' => ['Daily' => 'FREQ=DAILY;', 'Weekly' => 'FREQ=WEEKLY;', 'Monthly' => 'FREQ=MONTHLY;']
            ])
            ->add('end', DateType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Rrule::class,
        ));
    }

}