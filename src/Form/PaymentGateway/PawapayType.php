<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class PawapayType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('pawapay_api_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.pawapay_api_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ]);
    }
}


