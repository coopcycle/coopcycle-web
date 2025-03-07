<?php

namespace AppBundle\Form;

use AppBundle\Form\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewCustomOrderType extends DeliveryType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('variantName', TextType::class, [
                'label' => 'form.new_order.variant_name.label',
                'help' => 'form.new_order.variant_name.help',
                'mapped' => false,
            ])
            ->add('variantPrice', MoneyType::class, [
                'label' => 'form.new_order.variant_price.label',
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'with_tags' => false,
            'with_address_props' => true,
            'asap_timing' => true,
        ]);
    }
}
