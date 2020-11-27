<?php

namespace AppBundle\Form\Restaurant;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

trait FulfillmentMethodsTrait
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('enabledFulfillmentMethods', ChoiceType::class, [
                    'choices'  => [
                        'fulfillment_method.delivery' => 'delivery',
                        'fulfillment_method.collection' => 'collection',
                    ],
                    'choice_attr' => function($choice, $key, $value) {

                        return [
                            'data-enable-fulfillment-method' => $value,
                        ];
                    },
                    'label' => 'restaurant.form.fulfillment_methods.label',
                    'required' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'mapped' => false,
                ]);
        }

        $builder
            ->add('fulfillmentMethods', CollectionType::class, [
                'entry_type' => FulfillmentMethodType::class,
                'entry_options' => [
                    'label' => false,
                    'block_prefix' => 'fulfillment_method_item',
                ],
                'allow_add' => false,
                'allow_delete' => false,
                'prototype' => false,
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $object = $event->getData();
            $form = $event->getForm();

            if ($form->has('enabledFulfillmentMethods')) {

                $enabledFulfillmentMethods = [];
                if ($object->isFulfillmentMethodEnabled('delivery')) {
                    $enabledFulfillmentMethods[] = 'delivery';
                }
                if ($object->isFulfillmentMethodEnabled('collection')) {
                    $enabledFulfillmentMethods[] = 'collection';
                }

                $form->get('enabledFulfillmentMethods')->setData($enabledFulfillmentMethods);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $object = $form->getData();

            if ($form->has('enabledFulfillmentMethods')) {
                $enabledFulfillmentMethods = $form->get('enabledFulfillmentMethods')->getData();

                $object->addFulfillmentMethod('delivery', in_array('delivery', $enabledFulfillmentMethods));
                $object->addFulfillmentMethod('collection', in_array('collection', $enabledFulfillmentMethods));
            }
        });
    }
}
