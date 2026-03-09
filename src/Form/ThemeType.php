<?php

namespace AppBundle\Form;

use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ThemeType extends AbstractType
{
    public function __construct(
        private SettingsManager $settingsManager)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('primary', TextType::class, [
                'label' => 'form.theme.primary.label',
                'help' => 'form.theme.primary.help',
                'required' => false,
            ])
            ->add('primary-content', TextType::class, [
                'label' => 'form.theme.primary-content.label',
                'required' => false,
            ])
            ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            $theme = $this->settingsManager->get('theme');

            if ($theme) {
                $theme = json_decode($theme, true);
                $event->setData($theme);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $theme = $event->getData();

            $this->settingsManager->set('theme', json_encode($theme));
        });
    }
}
