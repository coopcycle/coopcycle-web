<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewOrderType extends DeliveryType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options = array_merge($options, [
            'with_tags' => false,
            'with_address_props' => true,
        ]);

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

        $builder->remove('weight');
    }
}
