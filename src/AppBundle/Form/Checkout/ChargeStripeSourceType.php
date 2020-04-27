<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use AppBundle\Service\StripeManager;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;

class ChargeStripeSourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

            $form
                ->add('source', HiddenType::class, [
                    'mapped' => false,
                    'data' => $payment->getSource(),
                ]);
        });
    }
}
