<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class StripeType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('stripe_test_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_test_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_test_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ]);
    }
}
