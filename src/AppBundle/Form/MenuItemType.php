<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Form\MenuType\MenuItemModifierType;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.menu_item.name.label'])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('price', MoneyType::class, ['label' => 'form.menu_item.price.label'])
            ->add('taxCategory', TaxCategoryChoiceType::class)
            ->add('isAvailable', CheckboxType::class, [
                'label' => 'Available product?',
                'required' => false
            ])
            ->add('modifiers', CollectionType::class, [
                'entry_type' => MenuItemModifierType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => MenuItem::class,
        ));
    }
}
