<?php

namespace AppBundle\Form\Restaurant;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoopeatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('enabled', CheckboxType::class, [
            'label' => 'restaurant.form.loopeat_enabled.label',
            'required' => false,
            'disabled' => !$options['allow_toggle'],
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();
            $restaurant = $parentForm->getData();

            $form->get('enabled')->setData($restaurant->isLoopeatEnabled());

            if ($restaurant->hasLoopEatCredentials()) {
                $form
                    ->add('disconnect', SubmitType::class, [
                        'label' => 'restaurant.form.loopeat_disconnect.label',
                    ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();
            $restaurant = $parentForm->getData();

            $enabled = $form->get('enabled')->getData();
            $restaurant->setLoopeatEnabled($enabled);

            if ($form->getClickedButton() && 'loopeatDisconnect' === $form->getClickedButton()->getName()) {
                $restaurant->clearLoopEatCredentials();
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
