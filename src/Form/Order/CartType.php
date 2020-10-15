<?php

namespace AppBundle\Form\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Form\AddressType;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CartType extends AbstractType
{
    private $orderProcessor;

    public function __construct(OrderProcessorInterface $orderProcessor)
    {
        $this->orderProcessor = $orderProcessor;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shippingAddress', AddressType::class, [
                'label' => false
            ])
            ->add('timeSlot', HiddenType::class, [
                'required' => false,
                'mapped' => false,
                // TODO Default value?
            ])
            ->add('isNewAddress', HiddenType::class, [
                'mapped' => false,
                'empty_data' => true
            ]);

        $removeAddressFields = function (FormEvent $event) {
            $form = $event->getForm();

            $form->get('shippingAddress')->remove('description');
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $removeAddressFields);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $cart = $event->getData();

            $shippingAddress = $form->get('shippingAddress')->getData();

            if ($shippingAddress && null !== $shippingAddress->getId()) {
                $form->get('isNewAddress')->setData(false);
            }

            $target = $cart->getTarget();
            $isCollectionOnly =
                $target->isFulfillmentMethodEnabled('collection') && !$target->isFulfillmentMethodEnabled('delivery');

            if ($target->isFulfillmentMethodEnabled('collection')) {
                $form->add('takeaway', CheckboxType::class, [
                    'required' => false,
                    'data' => $isCollectionOnly ? true : $cart->isTakeaway(),
                ]);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($removeAddressFields) {

            $form = $event->getForm();
            $data = $event->getData();

            $isNewAddress = isset($data['isNewAddress']) ? (bool) $data['isNewAddress'] : true;

            if (!$isNewAddress) {

                $shippingAddressForm = $form->get('shippingAddress');

                $config = $shippingAddressForm->getConfig();
                $options = $config->getOptions();
                $options['mapped'] = false;

                $form->add('shippingAddress', get_class($config->getType()->getInnerType()), $options);

                $removeAddressFields($event);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $form->getData();

            $timeSlot = $form->get('timeSlot')->getData();

            $pattern = '/^(?<date>[0-9]{4}-[0-9]{2}-[0-9]{2}) (?<start>[0-9]{2}:[0-9]{2})-(?<end>[0-9]{2}:[0-9]{2})$/';

            if (1 === preg_match($pattern, $timeSlot, $matches)) {

                $date = $matches['date'];
                $start = $matches['start'];
                $end = $matches['end'];

                $range = new TsRange();
                $range->setLower(new \DateTime(sprintf('%s %s:00', $matches['date'], $matches['start'])));
                $range->setUpper(new \DateTime(sprintf('%s %s:00', $matches['date'], $matches['end'])));

                $order->setShippingTimeRange($range);
            }

            $this->orderProcessor->process($order);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => OrderInterface::class,
            ]);
    }
}
