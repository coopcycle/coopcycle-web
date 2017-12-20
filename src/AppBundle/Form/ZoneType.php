<?php

namespace AppBundle\Form;

use AppBundle\Entity\Zone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', FormType\TextType::class)
            ->add('feature', FormType\HiddenType::class, [
                'mapped' => false
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $zone = $event->getData();
            $feature = $event->getForm()->get('feature')->getData();

            $zone->setGeoJSON(json_decode($feature, true));
            $event->setData($zone);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $zone = $event->getData();
            if ($zone) {
                $form->get('feature')->setData(json_encode($zone->getGeoJSON()));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Zone::class,
        ));
    }
}
