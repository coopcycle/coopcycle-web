<?php

namespace AppBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Order;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('createDeliveryAddress', ChoiceType::class, [
                'choices' => [
                    'Existing address' => false,
                    'New address' => true,
                ],
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choice_translation_domain' => true,
            ])
            ->add('save', SubmitType::class, array('label' => 'Continue to payment'));

        $formModifier = function (FormInterface $form, Order $order, $createDeliveryAddress) {

            if (null === $createDeliveryAddress) {
                $createDeliveryAddress = true;
                if (null !== $order->getDelivery() && null !== $order->getDelivery()->getDeliveryAddress()) {
                    $createDeliveryAddress = false;
                }
                if (count($order->getCustomer()->getAddresses()) > 0) {
                    $createDeliveryAddress = false;
                }

                $form->get('createDeliveryAddress')->setData($createDeliveryAddress);
            }

            // The deliveryAddress field is not mapped
            if ($createDeliveryAddress) {
                $form->add('deliveryAddress', AddressType::class, [ 'mapped' => false ]);
            } else {
                $form->add('deliveryAddress', EntityType::class, array(
                    'class' => 'AppBundle:Address',
                    'choices' => $order->getCustomer()->getAddresses(),
                    'choice_label' => function ($deliveryAddress) {
                        return $deliveryAddress->getStreetAddress();
                    },
                    'expanded' => true,
                    'multiple' => false,
                    'mapped' => false
                ));
            }
        };

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) use ($formModifier) {
                $order = $event->getData();
                $createDeliveryAddress = $event->getForm()->get('createDeliveryAddress')->getData();
                $formModifier($event->getForm(), $order, $createDeliveryAddress);
            }
        );

        $builder->get('createDeliveryAddress')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifier) {
                $parent = $event->getForm()->getParent();
                $createDeliveryAddress = $event->getForm()->getData();
                $formModifier($parent, $parent->getData(), $createDeliveryAddress);
            }
        );

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Order::class,
        ));
    }
}

