<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutocompleteAdapterType extends AbstractType
{
    private $autocompleteAdapter;
    private $algoliaApiKey;
    private $locationIqAccessToken;

    public function __construct(
        string $autocompleteAdapter,
        string $algoliaApiKey,
        string $locationIqAccessToken,
        string $geocodeEarthApiKey)
    {
        $this->autocompleteAdapter = $autocompleteAdapter;
        $this->algoliaApiKey = $algoliaApiKey;
        $this->locationIqAccessToken = $locationIqAccessToken;
        $this->geocodeEarthApiKey = $geocodeEarthApiKey;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            if (!$data) {
                $event->setData($this->autocompleteAdapter);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $choices = [];

        if (!empty($this->algoliaApiKey)) {
            $choices['Algolia'] = 'algolia';
        }

        if (!empty($this->locationIqAccessToken)) {
            $choices['LocationIQ'] = 'locationiq';
        }

        if (!empty($this->geocodeEarthApiKey)) {
            $choices['Geocode.Earth'] = 'geocode-earth';
        }

        $choices['Google'] = 'google';

        $resolver->setDefaults([
            'choices' => $choices,
            'required' => false,
            'label' => 'form.settings.autocomplete_provider.label',
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
