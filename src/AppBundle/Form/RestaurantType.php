<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\DeliveryService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Vich\UploaderBundle\Form\Type\VichImageType;

class RestaurantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('website', UrlType::class, ['required' => false])
            ->add('address', AddressType::class)
            ->add('deliveryService', DeliveryServiceType::class, ['mapped' => false])
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'download_uri' => false,
            ])
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
            ]);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {

                $restaurant = $event->getForm()->getData();

                $type = $event->getForm()->get('deliveryService')->get('type')->getData();

                if ($type === 'applicolis') {
                    $token = $event->getForm()->get('deliveryService')->get('token')->getData();
                    $deliveryService = $restaurant->getDeliveryService();

                    if (null === $deliveryService) {
                        $deliveryService = new DeliveryService\AppliColis();
                        $restaurant->setDeliveryService($deliveryService);
                    }

                    $deliveryService->setToken($token);
                }

                // Make sure there is no NULL value in the openingHours array
                $openingHours = array_filter($restaurant->getOpeningHours());
                $restaurant->setOpeningHours($openingHours);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
