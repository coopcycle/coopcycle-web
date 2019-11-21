<?php

namespace AppBundle\Form\Order;

use AppBundle\Form\AddressType;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
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
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'required' => false,
                'mapped' => false
            ])
            ->add('time', TimeType::class, [
                'widget' => 'single_text',
                'with_seconds' => false,
                'required' => false,
                'mapped' => false
            ])
            ->add('isNewAddress', HiddenType::class, [
                'mapped' => false,
                'empty_data' => true
            ]);

        $removeAddressFields = function (FormEvent $event) {
            $form = $event->getForm();

            $form->get('shippingAddress')->remove('floor');
            $form->get('shippingAddress')->remove('description');
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $removeAddressFields);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            $shippingAddress = $form->get('shippingAddress')->getData();

            if ($shippingAddress && null !== $shippingAddress->getId()) {
                $form->get('isNewAddress')->setData(false);
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

            $date = $form->get('date')->getData();
            $time = $form->get('time')->getData();

            if ($date && $time) {
                $order->setShippedAt(new \DateTime(sprintf('%s %s', $date->format('Y-m-d'), $time->format('H:i:00'))));
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
