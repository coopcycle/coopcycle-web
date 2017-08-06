<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use AppBundle\Entity\MenuSection;
use AppBundle\Entity\MenuItem;
use AppBundle\Form\MenuType\MenuSectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('addSection', EntityType::class, array(
                'class' => 'AppBundle:MenuSection',
                'choice_label' => 'name',
                'mapped' => false
            ))
            ->add('hasMenuSection', CollectionType::class, [
                'entry_type' => MenuSectionType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => false,
                'label' => false
            ]);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Menu::class,
        ));
    }
}
