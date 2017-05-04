<?php

namespace AppBundle\Form;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\GeoCoordinates;
use Vich\UploaderBundle\Form\Type\VichImageType;

class RestaurantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('website', UrlType::class, ['required' => false])
            // FoodEstablishment
            ->add('servesCuisine', CollectionType::class, array(
                'entry_type' => EntityType::class,
                'entry_options' => array(
                    'label' => 'Cuisine',
                    'class' => 'AppBundle:Cuisine',
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('c')->orderBy('c.name', 'ASC');
                    },
                ),
                'allow_add' => true,
                'allow_delete' => true,
            ))
            // LocalBusiness
            ->add('telephone', TextType::class, ['required' => false])
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
            ])
            // PostalAddress
            ->add('streetAddress', TextType::class)
            ->add('postalCode', TextType::class)
            ->add('addressLocality', TextType::class, ['label' => 'City'])
            ->add('latitude', HiddenType::class, ['mapped' => false])
            ->add('longitude', HiddenType::class, ['mapped' => false])
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'download_link' => false,
            ])
            ;

        $constraints = [
            new Constraints\NotBlank(),
            new Constraints\Type(['type' => 'numeric']),
        ];

        // Make sure latitude/longitude is valid
        $latLngListener = function (FormEvent $event) use ($constraints) {
            $restaurant = $event->getData();
            $form = $event->getForm();

            $streetAddress = $form->get('streetAddress')->getData();
            if (!empty($streetAddress)) {

                $latitude = $form->get('latitude')->getData();
                $longitude = $form->get('longitude')->getData();

                $validator = Validation::createValidator();

                $latitudeViolations = $validator->validate($latitude, $constraints);
                $longitudeViolations = $validator->validate($longitude, $constraints);

                if (count($latitudeViolations) === 0 && count($longitudeViolations) === 0) {
                    $restaurant->setGeo(new GeoCoordinates($latitude, $longitude));
                } else {
                    $form->get('streetAddress')
                    ->addError(new FormError('Please select an address in the dropdown'));
                }
            }
        };

        $builder->addEventListener(FormEvents::POST_SUBMIT, $latLngListener);
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $restaurant = $event->getData();
            if (null !== $restaurant) {
                $form = $event->getForm();
                if ($geo = $restaurant->getGeo()) {
                    $form->get('latitude')->setData($geo->getLatitude());
                    $form->get('longitude')->setData($geo->getLongitude());
                }

            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
