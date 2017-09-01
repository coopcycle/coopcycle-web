<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('givenName', TextType::class)
            ->add('familyName', TextType::class)
            ->add('telephone', TextType::class)
            ->add('accountType', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'choices'  => [
                    'Customer' => 'CUSTOMER',
                    'Courier' => 'COURIER',
                    'Restaurant' => 'RESTAURANT',
                ]
            ]);
    }

    public function getParent()
    {
        return 'FOS\UserBundle\Form\Type\RegistrationFormType';

    }

    public function getBlockPrefix()
    {
        return 'app_user_registration';
    }

    // For Symfony 2.x
    public function getName()
    {
        return $this->getBlockPrefix();
    }
}
