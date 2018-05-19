<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductOptionValue;
use Sylius\Bundle\ProductBundle\Form\Type\ProductOptionValueTranslationType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductOptionValueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => ProductOptionValueTranslationType::class,
            ])
            ->add('price', MoneyType::class, [
                'label' => 'form.product_option_value.price.label',
                'divisor' => 100,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductOptionValue::class,
        ));
    }
}
