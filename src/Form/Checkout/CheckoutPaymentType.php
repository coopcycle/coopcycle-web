<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Payment\PaymentMethodsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckoutPaymentType extends AbstractType
{
    public function __construct(
        private GatewayResolver $gatewayResolver,
        private PaymentMethodsResolver $paymentMethodsResolver
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('stripePayment', StripePaymentType::class, ['label' => false]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $checkoutPayment = $event->getData();
            $order = $checkoutPayment->getOrder();

            $choices = [];

            $choiceAttrByValue = [];
            foreach ($this->paymentMethodsResolver->resolveForCheckout($order) as $resolvedMethod) {
                $type = $resolvedMethod->getType();

                $choiceAttrByValue[$type] = $resolvedMethod->getChoiceAttr();

                switch ($type) {
                    case 'card':
                        $choices['Credit card'] = $type;
                        break;
                    case 'edenred':
                        $choices['Edenred'] = $type;
                        break;
                    case 'restoflash':
                        $choices['Restoflash'] = $type;
                        break;
                    case 'conecs':
                        $choices['Conecs'] = $type;
                        break;
                    case 'swile':
                        $choices['Swile'] = $type;
                        break;
                    case 'cash_on_delivery':
                        $choices['Cash on delivery'] = $type;
                        break;
                    default:
                        // Ignore types not supported by the web checkout UI (for now).
                        break;
                }
            }

            switch ($this->gatewayResolver->resolveForOrder($order)) {
                case 'mercadopago':
                    // @see https://www.mercadopago.com.br/developers/en/guides/payments/api/receiving-payment-by-card/
                    $form
                        ->add('paymentMethod', HiddenType::class, [
                            'mapped' => false,
                        ])
                        ->add('installments', HiddenType::class, [
                            'mapped' => false,
                        ])
                        ->add('issuer', HiddenType::class, [
                            'mapped' => false,
                        ])
                        ->add('payerEmail', HiddenType::class, [
                            'mapped' => false,
                        ]);
                    break;
            }

            $form
                ->add('method', ChoiceType::class, [
                    'label' => count($choices) > 1 ? 'form.checkout_payment.method.label' : false,
                    'choices' => $choices,
                    'choice_attr' => fn ($choice, $key, $value) => $choiceAttrByValue[$value] ?? [],
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'data' => count($choices) === 1 ? array_values($choices)[0] : null
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
