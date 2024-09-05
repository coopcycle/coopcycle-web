<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PaygreenType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('paygreen_public_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_public_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('paygreen_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('paygreen_shop_id', TextType::class, [
                'required' => false,
                'label' => 'form.settings.paygreen_shop_id.label',
            ]);
    }
}

