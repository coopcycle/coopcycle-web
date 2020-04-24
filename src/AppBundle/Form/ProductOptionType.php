<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Form\Type\MinMaxType;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductOptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $strategies = [
            ProductOptionInterface::STRATEGY_FREE,
            ProductOptionInterface::STRATEGY_OPTION_VALUE
        ];

        $strategyChoices = [];
        foreach ($strategies as $strategy) {
            $strategyChoices[sprintf('product_option.strategy.%s', $strategy)] = $strategy;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.product_option.name.label'
            ])
            ->add('strategy', ChoiceType::class, [
                'choices' => $strategyChoices,
                'label' => 'form.product_option.strategy.label'
            ])
            ->add('valuesRange', MinMaxType::class)
            ->add('values', CollectionType::class, [
                'entry_type' => ProductOptionValueType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                // 'button_add_label' => 'sylius.form.option_value.add_value',
            ])
            ->add('additional', CheckboxType::class, [
                'required' => false,
                'label' => 'form.product_option.additional.label',
                'help' => 'form.product_option.additional.help'
            ])
            ->add('delete', SubmitType::class, [
                'label' => 'basics.delete',
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $productOption = $event->getForm()->getData();
            if (!$productOption->isAdditional()) {
                $productOption->setValuesRange(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductOption::class,
        ));
    }
}
