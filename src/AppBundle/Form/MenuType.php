<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use AppBundle\Entity\MenuSection;
use AppBundle\Entity\MenuItem;
use AppBundle\Form\MenuType\MenuSectionType;
use Doctrine\ORM\EntityRepository;
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
            ->add('sections', CollectionType::class, [
                'entry_type' => MenuSectionType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => false,
                'label' => false,
                'attr' => [
                    'data-section-added' => $options['section_added'] ? $options['section_added']->getId() : ''
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $menu = $event->getData();
            $form = $event->getForm();

            $menuSectionIds = [];
            foreach ($menu->getSections() as $section) {
                $menuSectionIds[] = $section->getMenuSection()->getId();
            }

            $form->add('addSection', EntityType::class, array(
                'mapped' => false,
                'class' => 'AppBundle:MenuSection',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($menuSectionIds) {
                    $qb = $er->createQueryBuilder('s');
                    if (count($menuSectionIds) > 0) {
                        $qb->where($qb->expr()->notIn('s.id', $menuSectionIds));
                    }

                    return $qb->orderBy('s.name', 'ASC');
                }
            ));
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Menu::class,
            'section_added' => null
        ));
    }
}
