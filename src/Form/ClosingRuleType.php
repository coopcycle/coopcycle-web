<?php

namespace AppBundle\Form;

use AppBundle\Entity\ClosingRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClosingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'startDate',
            DateTimeType::class,
            ['attr' => ['style' => 'display:none;'], 'label' => false]);
        $builder->add(
            'endDate',
            DateTimeType::class,
            ['attr' => ['style' => 'display:none;'], 'label' => false]);
        $builder->add(
            'reason',
            TextType::class,
            ['label' => 'restaurant.closingForm.reason.label',
             'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ClosingRule::class
        ]);
    }

}
