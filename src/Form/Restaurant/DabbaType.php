<?php

namespace AppBundle\Form\Restaurant;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DabbaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('enabled', CheckboxType::class, [
            'label' => 'restaurant.form.dabba_enabled.label',
            'required' => false,
            'disabled' => !$options['allow_toggle'],
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();
            $restaurant = $parentForm->getData();

            $form->get('enabled')->setData($restaurant->isDabbaEnabled());

            if ($restaurant->isDabbaEnabled()) {
                $form->add('dabbaCode', TextType::class, [
                    'label' => 'restaurant.form.dabba_code.label',
                    'required' => false,
                    'data' => $restaurant->getDabbaCode(),
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();
            $restaurant = $parentForm->getData();

            $enabled = $form->get('enabled')->getData();
            $restaurant->setDabbaEnabled($enabled);

            if ($form->has('dabbaCode')) {
                $dabbaCode = $form->get('dabbaCode')->getData();
                $restaurant->setDabbaCode($dabbaCode);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'allow_toggle' => false,
        ));
    }
}
