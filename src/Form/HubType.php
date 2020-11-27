<?php

namespace AppBundle\Form;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Restaurant\ShippingOptionsTrait;
use AppBundle\Form\Restaurant\FulfillmentMethodType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HubType extends AbstractType
{
    use ShippingOptionsTrait {
        buildForm as buildShippingOptionsForm;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->buildShippingOptionsForm($builder, $options);

        $builder
            ->add('name', TextType::class, ['label' => 'basics.name'])
            ->add('restaurants', CollectionType::class, [
                'entry_type' => EntityType::class,
                'entry_options' => [
                    'label' => false,
                    'class' => LocalBusiness::class,
                    'choice_label' => 'name',
                ],
                'label' => 'form.hub.restaurants.label',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Hub::class,
        ));
    }
}
