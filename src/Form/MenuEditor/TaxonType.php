<?php

namespace AppBundle\Form\MenuEditor;

use AppBundle\Entity\Sylius\Taxon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['data-prop' => 'name']]
            )
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['data-prop' => 'description']]
            )
            ->add('position', IntegerType::class)
            ->add('taxonProducts', CollectionType::class, [
                'entry_type' => TaxonProductType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__taxonProducts__',
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Taxon::class,
        ));
    }
}
