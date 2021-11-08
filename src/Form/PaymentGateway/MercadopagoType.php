<?php

namespace AppBundle\Form\PaymentGateway;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class MercadopagoType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('mercadopago_test_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('mercadopago_live_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('mercadopago_test_access_token', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.access_token.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('mercadopago_live_access_token', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.access_token.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('mercadopago_app_id', TextType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.app_id.label',
                'help' => 'form.settings.mercadopago.app_id.help',
                'help_html' => true
            ])
            ->add('mercadopago_client_secret', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.mercadopago.client_secret.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();

            $settings = $parentForm->getData();

            $form->get('mercadopago_app_id')->setData($settings->mercadopago_app_id);
        });
    }
}
