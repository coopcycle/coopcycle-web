<?php

namespace AppBundle\Form;

use Sylius\Component\Taxonomy\Model\Taxon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuTaxonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $menuTaxon = $event->getData();

            if (null !== $menuTaxon->getId()) {
                $form
                    ->add('delete', SubmitType::class, [
                        'label' => 'basics.delete',
                    ])
                    ;
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
