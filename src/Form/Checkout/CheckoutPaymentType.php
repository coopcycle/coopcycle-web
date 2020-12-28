<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\StripeManager;
use AppBundle\Utils\OrderTimeHelper;
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

    public function __construct(StripeManager $stripeManager, GatewayResolver $resolver, OrderTimeHelper $orderTimeHelper)
    {
        $this->stripeManager = $stripeManager;
        $this->resolver = $resolver;

        parent::__construct($orderTimeHelper);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

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
    }
}
