<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\DeliveryService;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class DeliveryServiceType extends AbstractType
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
            ->add('type', ChoiceType::class, [
                'choices'  => $deliveryServices,
                'mapped' => false
            ]);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $parentForm = $event->getForm()->getParent();
                $restaurant = $parentForm->getData();

                $type = $restaurant->getDeliveryService() ? $restaurant->getDeliveryService()->getType() : 'core';
                $event->getForm()->get('type')->setData($type);

                if ('applicolis' === $type) {
                    $event->getForm()->add('token', TextType::class, [
                        'data' => $restaurant->getDeliveryService()->getToken()
                    ]);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {
                $restaurant = $event->getForm()->getParent()->getData();
                $type = $restaurant->getDeliveryService() ? $restaurant->getDeliveryService()->getType() : 'core';
                $event->getForm()->get('type')->setData($type);
            }
        );

        $builder->get('type')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $type = $event->getForm()->getData();

                if ('applicolis' === $type) {
                    $event->getForm()->getParent()->add('token', TextType::class);
                }
            }
        );
    }
}
