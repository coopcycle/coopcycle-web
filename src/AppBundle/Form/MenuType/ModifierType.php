<?php

namespace AppBundle\Form\MenuType;

use AppBundle\Entity\Menu\Modifier;
use AppBundle\Form\MenuItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModifierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices =
        $builder
            ->add('name', TextType::class)
            ->add('price', MoneyType::class)
            ->add('description', TextType::class)
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Modifier::class,
        ));
    }
}
