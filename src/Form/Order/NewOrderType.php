<?php

namespace AppBundle\Form\Order;

use AppBundle\Form\DeliveryType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewOrderType extends DeliveryType
{
    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator,
        AuthorizationCheckerInterface $authorizationChecker,
        string $country,
        string $locale,
        OrderManager $orderManager)
    {
        parent::__construct($routing, $translator, $authorizationChecker, $country, $locale, $orderManager);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'with_dropoff_doorstep' => true,
            'with_remember_address' => true,
            'with_address_props' => true,
            'with_arbitrary_price' => true,
            'with_bookmark' => true,
            'with_recurrence' => true
        ]);
    }
}
