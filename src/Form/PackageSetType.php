<?php

namespace AppBundle\Form;

use AppBundle\Entity\PackageSet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PackageSetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => "form.package_set.name.label",
                'label_html' => true,
            ])
            ->add('packages', CollectionType::class, [
                'entry_type' => PackageType::class,
                'entry_options' => ['label' => false],
                'label' => 'form.package_set.packages.label',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PackageSet::class,
        ));
    }
}
