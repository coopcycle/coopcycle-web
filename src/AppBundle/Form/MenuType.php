<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use AppBundle\Form\MenuType\MenuSectionType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('addSection', TextType::class, array(
                'mapped' => false,
                'required' => false,
            ))
            ->add('suggestions', EntityType::class, array(
                'mapped' => false,
                'class' => Menu\MenuCategory::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')->orderBy('s.name', 'ASC');
                }
            ));

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $event->getForm()->add('sections', CollectionType::class, [
                'entry_type' => MenuSectionType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => false,
                'label' => false,
                'attr' => [
                    'data-section-added' => $options['section_added'] ? $options['section_added']->getId() : ''
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Menu::class,
            'section_added' => null,
        ));
    }
}
