<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class PaygreenType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('paygreen_test_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_client_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('paygreen_test_secret', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('paygreen_live_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_client_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('paygreen_live_secret', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ]);
    }
}
