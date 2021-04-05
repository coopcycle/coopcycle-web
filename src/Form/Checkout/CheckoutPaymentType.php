<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Edenred\Client as EdenredPayment;
use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use AppBundle\Utils\OrderTimeHelper;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Webmozart\Assert\Assert;

class CheckoutPaymentType extends AbstractType
{
    private $resolver;

    public function __construct(
        GatewayResolver $resolver,
        OrderTimeHelper $orderTimeHelper,
        EdenredAuthentication $edenredAuthentication,
        EdenredPayment $edenredPayment)
    {
        $this->resolver = $resolver;
        $this->edenredAuthentication = $edenredAuthentication;
        $this->edenredPayment = $edenredPayment;

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

            if (!$order->hasVendor()) {

                return;
            }

            $vendor = $order->getVendor();

            $choices = [
                'Credit card' => 'card',
            ];

            if ($vendor->isStripePaymentMethodEnabled('giropay')) {
                $choices['Giropay'] = 'giropay';
            }

            if (!$order->isMultiVendor() && null !== $vendor->getEdenredMerchantId()) {
                if ($order->getCustomer()->hasEdenredCredentials()) {
                    $amounts = $this->edenredPayment->splitAmounts($order);
                    if ($amounts['edenred'] > 0) {
                        if ($amounts['card'] > 0) {
                            $choices['Edenred'] = PaymentContext::METHOD_EDENRED_PLUS_CARD;
                        } else {
                            $choices['Edenred'] = PaymentContext::METHOD_EDENRED;
                        }
                    }
                } else {
                    // The customer will be presented with the button
                    // to connect his/her Edenred account
                    $choices['Edenred'] = 'edenred';
                }
            }

            if (count($choices) < 2) {
                return;
            }

            $form
                ->add('method', ChoiceType::class, [
                    'label' => 'form.checkout_payment.method.label',
                    'choices' => $choices,
                    'choice_attr' => function($choice, $key, $value) use ($order) {

                        Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

                        switch ($value) {
                            case PaymentContext::METHOD_EDENRED:
                            case PaymentContext::METHOD_EDENRED_PLUS_CARD:
                                return [
                                    'data-edenred-is-connected' => $order->getCustomer()->hasEdenredCredentials(),
                                    'data-edenred-authorize-url' => $this->edenredAuthentication->getAuthorizeUrl($order)
                                ];
                        }

                        return [];
                    },
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                ]);
        });
    }
}
