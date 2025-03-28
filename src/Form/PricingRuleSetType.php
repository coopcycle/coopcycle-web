<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\FormFieldUtils;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PricingRuleSetType extends AbstractType
{

    public function __construct(
        private readonly FormFieldUtils $formFieldUtils,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.pricing_rule_set.name.label'
            ])
            ->add('strategy', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    'form.pricing_rule_set.strategy.find.label' => 'find',
                    'form.pricing_rule_set.strategy.map.label' => 'map',
                ],
                ...$this->formFieldUtils->getLabelWithLinkToDocs('form.pricing_rule_set.strategy.label', 'form.pricing_rule_set.strategy.docs_path'),
                'help' => 'form.pricing_rule_set.strategy.help',
                'multiple' => false,
                'expanded' => true,
            ])
            // There is no options for now
//            ->add('options', ChoiceType::class, [
//                'required' => false,
//                'choices'  => [
//                ],
//                'label' => 'form.pricing_rule_set.options.label',
//                'multiple' => true,
//                'expanded' => true,
//            ])
            ->add('rules', CollectionType::class, array(
                ...$this->formFieldUtils->getLabelWithLinkToDocs('form.pricing_rule_set.rules.label', 'form.pricing_rule_set.rules.docs_path'),
                'entry_type' => PricingRuleType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PricingRuleSet::class,
        ));
    }
}
