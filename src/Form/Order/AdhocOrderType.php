<?php

namespace AppBundle\Form\Order;

use AppBundle\Form\AddressType;
use AppBundle\Form\StripePaymentType;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Form\Type\TsRangeChoice;
use AppBundle\Translation\DatePeriodFormatter;
use AppBundle\Utils\OrderTimeHelper;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdhocOrderType extends AbstractType
{
    private $orderProcessor;
    private $datePeriodFormatter;
    private $orderTimeHelper;

    public function __construct(
        OrderProcessorInterface $orderProcessor,
        DatePeriodFormatter $datePeriodFormatter,
        OrderTimeHelper $orderTimeHelper)
    {
        $this->orderProcessor = $orderProcessor;
        $this->datePeriodFormatter = $datePeriodFormatter;
        $this->orderTimeHelper = $orderTimeHelper;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shippingAddress', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
            ])
            ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $cart = $event->getData();

            $choices = $this->orderTimeHelper->getShippingTimeRanges($cart);
            $choices = array_map(fn ($tsRange) => new TsRangeChoice($tsRange), $choices);

            $payment = $cart->getLastPayment();
            $pendingPayment = in_array($payment->getState(), [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW]);

            $form->add('shippingTimeRange', ChoiceType::class, [
                'label' => 'form.delivery.time_slot.label',
                'choices' => $choices,
                'choice_label' => function(TsRangeChoice $choice) {
                    return $this->datePeriodFormatter->toHumanReadable($choice->toDatePeriod());
                },
                'choice_value' => function ($choice) {
                    return $choice;
                },
                'data' => $cart->getShippingTimeRange() !== null ?
                    new TsRangeChoice($cart->getShippingTimeRange()) : null,
                'mapped' => false,
                'disabled' => $options['with_payment'] || !$pendingPayment,
            ]);

            $form->add('shippingAddress', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => 'Dirección',
                'disabled' => $options['with_payment'] || !$pendingPayment,
            ]);

            if ($options['with_payment'] && $pendingPayment) {
                $form->add('payment', StripePaymentType::class, [
                    'mapped' => false,
                ]);
            }

        });

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {

                $form = $event->getForm();
                $order = $form->getData();

                $order->setShippingTimeRange($form->get('shippingTimeRange')->getData()->toTsRange());

                $this->orderProcessor->process($order);
            }
        );

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => OrderInterface::class,
                'with_payment' => false,
            ]);
    }
}
