<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use Sylius\Component\Taxonomy\Model\Taxon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuTaxonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.menu_taxon.name.label'
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $menuTaxon = $event->getData();

            if (null !== $menuTaxon->getId()) {
                $form
                    ->add('childName', TextType::class, [
                        'mapped' => false,
                        'attr' => [
                            'placeholder' => 'form.menu_taxon.child_name.placeholder'
                        ]
                    ])
                    ->add('addChild', SubmitType::class, [
                        'label' => 'form.menu_taxon.add_child.label'
                    ])
                    ->add('delete', SubmitType::class, [
                        'label' => 'basics.delete',
                    ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Taxon::class,
        ));
    }
}
