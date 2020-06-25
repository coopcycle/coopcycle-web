<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\ReusablePackaging;
use AppBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
// use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReusablePackagingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'basics.name'
            ])
            ->add('price', MoneyType::class, [
                'label' => 'basics.price',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ReusablePackaging::class,
        ));
    }
}
