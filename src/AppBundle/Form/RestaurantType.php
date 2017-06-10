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
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Vich\UploaderBundle\Form\Type\VichImageType;

class RestaurantType extends AbstractType
{
    private $translator;
    private $deliveryServiceFactory;

    public function __construct(TranslatorInterface $translator, DeliveryServiceFactory $deliveryServiceFactory)
    {
        $this->translator = $translator;
        $this->deliveryServiceFactory = $deliveryServiceFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $deliveryServices = [
            $this->translator->trans('Default') => null
        ];
        foreach ($this->deliveryServiceFactory->getServices() as $service) {
            $key = $this->translator->trans('delivery_service.' . $service->getKey());
            $deliveryServices[$key] = $service->getKey();
        }

        $builder
            ->add('name', TextType::class)
            ->add('website', UrlType::class, ['required' => false])
            ->add('address', AddressType::class)
            ->add('deliveryService', ChoiceType::class, [
                'choices'  => $deliveryServices
            ])
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'download_link' => false,
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
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
