<?php

namespace AppBundle\Form\MenuType;

use AppBundle\Entity\Menu\MenuItemModifier;
use AppBundle\Form\MenuItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

class MenuItemModifierType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices =
        $builder
            ->add('name', TextType::class, ['label' => 'form.menu_item_modifier.name.label'])
            ->add('price', MoneyType::class, ['label' => 'form.menu_item_modifier.price.label'])
            ->add('calculusStrategy', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('menu.modifier.STRATEGY_FREE') => MenuItemModifier::STRATEGY_FREE,
                    $this->translator->trans('menu.modifier.STRATEGY_ADD_MODIFIER_PRICE') => MenuItemModifier::STRATEGY_ADD_MODIFIER_PRICE,
                    $this->translator->trans('menu.modifier.STRATEGY_ADD_MENUITEM_PRICE') => MenuItemModifier::STRATEGY_ADD_MENUITEM_PRICE,
                ],
                'label' => 'form.menu_item_modifier.calculusStrategy.label'
            ])
            ->add('modifierChoices', CollectionType::class, [
                'entry_type' => ModifierType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => MenuItemModifier::class,
        ));
    }
}
