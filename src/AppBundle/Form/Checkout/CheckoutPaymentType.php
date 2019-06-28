<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Form\StripePaymentType;
use AppBundle\Utils\OrderTimeHelperTrait;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;

class CheckoutPaymentType extends AbstractType
{
    use OrderTimeHelperTrait;

    private $shippingDateFilter;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function __construct(
        ShippingDateFilter $shippingDateFilter,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->shippingDateFilter = $shippingDateFilter;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $order = $event->getForm()->getData();

            if (null === $order->getShippedAt()) {
                $availabilities = $this->getAvailabilities($order);
                $asap = $this->getAsap($availabilities);

                $order->setShippedAt(new \DateTime($asap));
            }
        });
    }
}
