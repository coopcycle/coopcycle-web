<?php

namespace AppBundle\Form;

use AppBundle\Entity\ReusablePackagings;
use AppBundle\Entity\ReusablePackaging;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReusablePackagingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reusablePackaging', EntityType::class, [
                'label' => 'form.product.reusable_packaging.label',
                'class' => ReusablePackaging::class,
                'choice_loader' => $options['reusable_packaging_choice_loader'],
                'choice_label' => 'name',
            ])
        	->add('units', NumberType::class, [
                'label' => 'form.product.reusable_packaging_unit.label',
                'html5' => true,
                'attr'  => array(
                    'min'  => 0,
                    'step' => $options['units_step'],
                )
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ReusablePackagings::class,
            'reusable_packaging_choice_loader' => null,
            'units_step' => 0.5,
        ));
    }
}
