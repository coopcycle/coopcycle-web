<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

class ProductOptionType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $strategies = [
            ProductOptionInterface::STRATEGY_FREE,
            ProductOptionInterface::STRATEGY_OPTION,
            ProductOptionInterface::STRATEGY_OPTION_VALUE
        ];

        $strategyChoices = [];
        foreach ($strategies as $strategy) {
            $strategyChoices[$this->translator->trans(sprintf('product_option.strategy.%s', $strategy))] = $strategy;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.product_option.name.label'
            ])
            ->add('strategy', ChoiceType::class, [
                'choices' => $strategyChoices,
                'label' => 'form.product_option.strategy.label'
            ])
            ->add('price', MoneyType::class, [
                'label' => 'form.product_option.price.label',
                'divisor' => 100,
                'required' => false
            ])
            ->add('values', CollectionType::class, [
                'entry_type' => ProductOptionValueType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                // 'button_add_label' => 'sylius.form.option_value.add_value',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductOption::class,
        ));
    }
}
