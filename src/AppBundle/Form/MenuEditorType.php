<?php

namespace AppBundle\Form;

use AppBundle\Form\MenuEditor\TaxonType;
use AppBundle\Utils\MenuEditor;
use Sylius\Component\Taxonomy\Model\Taxon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'help' => 'form.menu_editor.name.help',
            ])
            ->add('children', CollectionType::class, [
                'entry_type' => TaxonType::class,
                'allow_add' => false,
                'allow_delete' => false,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => MenuEditor::class,
        ));
    }
}
