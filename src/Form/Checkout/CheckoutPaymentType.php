<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Edenred\Client as EdenredPayment;
use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class CheckoutPaymentType extends AbstractType
{
    public function __construct(
        private GatewayResolver $resolver,
        private EdenredAuthentication $edenredAuthentication,
        private EdenredPayment $edenredPayment,
        private SettingsManager $settingsManager,
        private bool $cashEnabled)
    { }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('stripePayment', StripePaymentType::class, ['label' => false]);

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
            $checkoutPayment = $event->getData();
            $order = $checkoutPayment->getOrder();

            $choices = [];

            if ($this->settingsManager->supportsCardPayments()) {
                $choices['Credit card'] = 'card';
            }

            $hasValidEdenredCredentials = false;
            if ($order->supportsEdenred()) {
                $hasValidEdenredCredentials = $this->edenredPayment->hasValidCredentials($order->getCustomer());
                if ($hasValidEdenredCredentials) {
                    $edenredAmount = $this->edenredPayment->getMaxAmount($order);
                    if ($edenredAmount > 0) {
                        $choices['Edenred'] = 'edenred';
                    }
                } else {
                    // The customer will be presented with the button
                    // to connect his/her Edenred account
                    $choices['Edenred'] = 'edenred';
                }
            }

            if ($this->cashEnabled || $order->supportsCashOnDelivery()) {
                $choices['Cash on delivery'] = 'cash_on_delivery';
            }

            $form
                ->add('method', ChoiceType::class, [
                    'label' => count($choices) > 1 ? 'form.checkout_payment.method.label' : false,
                    'choices' => $choices,
                    'choice_attr' => function($choice, $key, $value) use ($order, $hasValidEdenredCredentials) {

                        if (null !== $order->getCustomer()) {

                            Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

                            switch ($value) {
                                case 'edenred':
                                    return [
                                        'data-edenred-is-connected' => $hasValidEdenredCredentials,
                                        'data-edenred-authorize-url' => $this->edenredAuthentication->getAuthorizeUrl($order)
                                    ];
                            }
                        }

                        return [];
                    },
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'data' => count($choices) === 1 ? 'card' : null
                ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => CheckoutPayment::class,
        ]);
    }
}
