<?php

namespace AppBundle\Form\Restaurant;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

trait ShippingOptionsTrait
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shippingOptionsDays', IntegerType::class, [
                'label' => 'localBusiness.form.shippingOptionsDays',
                'attr' => [
                    'min' => 1,
                    'max' => 6
                ]
            ]);
    }
}
