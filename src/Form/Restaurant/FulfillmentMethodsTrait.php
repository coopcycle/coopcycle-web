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
    }
}
