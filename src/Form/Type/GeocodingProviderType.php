<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeocodingProviderType extends AbstractType
{
    private $opencageApiKey;

    public function __construct(string $opencageApiKey)
    {
        $this->opencageApiKey = $opencageApiKey;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            if (!$data) {
                $event->setData('opencage');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $choices = [];

        if (!empty($this->opencageApiKey)) {
            $choices['OpenCage'] = 'opencage';
        }

        $choices['Google'] = 'google';

        $resolver->setDefaults([
            'choices' => $choices,
            'required' => false,
            'label' => 'form.settings.geocoding_provider.label',
            'help' => 'form.settings.geocoding_provider.help',
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
