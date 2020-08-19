<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StripeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
