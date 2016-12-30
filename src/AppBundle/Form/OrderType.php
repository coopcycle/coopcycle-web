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
                    'Adresse existante' => 0,
                    'Nouvelle adresse' => 1,
                ],
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('save', SubmitType::class, array('label' => 'Commander'));

        $formModifier = function (FormInterface $form, $order, $createDeliveryAddress = true) {
            if ($createDeliveryAddress || isset($order) && count($order->getCustomer()->getDeliveryAddresses()) === 0) {
                $form->add('deliveryAddress', DeliveryAddressType::class);
            } else {
                $customer = $order->getCustomer();

                $form->add('deliveryAddress', EntityType::class, array(
                    'class' => 'AppBundle:DeliveryAddress',
                    'query_builder' => function (EntityRepository $e) use ($customer) {
                        return $e->createQueryBuilder('d')
                            ->where('d.customer = :customer')
                            ->setParameter('customer', $customer)
                            ->orderBy('d.streetAddress', 'ASC');
                    },
                    'choice_label' => function ($deliveryAddress) {
                        return $deliveryAddress->getStreetAddress();
                    },
                    'expanded' => true,
                    'multiple' => false,
                ));
            }
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
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

