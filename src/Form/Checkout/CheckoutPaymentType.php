<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\StripeManager;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;

class CheckoutPaymentType extends AbstractType
{
    private $stripeManager;
    private $resolver;

    public function __construct(StripeManager $stripeManager, GatewayResolver $resolver)
    {
        $this->stripeManager = $stripeManager;
        $this->resolver = $resolver;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
            ]);

        // @see https://www.mercadopago.com.br/developers/en/guides/payments/api/receiving-payment-by-card/
        if ('mercadopago' === $this->resolver->resolve()) {
            $builder
                ->add('paymentMethod', HiddenType::class, [
                    'mapped' => false,
                ])
                ->add('installments', HiddenType::class, [
                    'mapped' => false,
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $restaurant = $order->getRestaurant();

            if (null === $restaurant) {

                return;
            }

            $choices = [
                'Credit card' => 'card',
            ];

            if ($restaurant->isStripePaymentMethodEnabled('giropay')) {
                $choices['Giropay'] = 'giropay';
            }

            if (count($choices) < 2) {
                return;
            }

            $form
                ->add('method', ChoiceType::class, [
                    'label' => 'form.checkout_payment.method.label',
                    'choices' => $choices,
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                ]);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

            if (!$form->has('method')) {

                // This is needed if customer has selected
                // another method previously, but didn't
                // complete the process
                $payment->clearSource();

                return;
            }

            if ('giropay' === $form->get('method')->getData()) {

                $ownerName = $form->get('stripePayment')
                    ->get('cardholderName')->getData();

                // TODO Catch Exception (source not enabled)
                $source = $this->stripeManager->createGiropaySource($payment, $ownerName);

                $payment->setSource($source);
            }
        });
    }
}
