<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant\PreparationTimeRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreparationTimeRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('expression', TextType::class, [
                'label' => 'form.preparation_time_rule.expression.label'
            ])
            ->add('time', TextType::class, [
                'label' => 'form.preparation_time_rule.time.label'
            ])
            ->add('position', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PreparationTimeRule::class,
        ));
    }
}
