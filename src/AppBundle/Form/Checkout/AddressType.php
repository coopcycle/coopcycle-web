<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Address;
use AppBundle\Form\AddressType as BaseAddressType;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('newAddress', BaseAddressType::class, [
            'mapped' => false,
            'required' => false,
            // 'class' => Address::class,
            // 'choices' => $order->getCustomer()->getAddresses(),
            // 'choice_label' => function (Address $address) {
            //     return $address->getStreetAddress();
            // }
        ]);

        $builder->add('createNewAddress', SubmitType::class, [
            'label' => 'Créer une nouvelle adresse',
        ]);

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {

                $form = $event->getForm();
                $order = $form->getData();

                $customerAddresses = $order->getCustomer()->getAddresses();
                $shippingAddress = $order->getShippingAddress();

                $options = [
                    'mapped' => false,
                    'required' => false,
                    'class' => Address::class,
                    'choices' => $order->getCustomer()->getAddresses(),
                    'choice_label' => function (Address $address) {
                        return $address->getStreetAddress();
                    },
                    'expanded' => true,
                    'multiple' => false
                ];

                if (null !== $shippingAddress && $customerAddresses->contains($shippingAddress)) {
                    $options['data'] = $shippingAddress;
                }

                if (count($customerAddresses) > 0) {
                    $form->add('existingAddress', EntityType::class, $options);
                    $form->add('chooseExistingAddress', SubmitType::class, [
                        'label' => 'Choisir une adresse existante',
                    ]);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {

                $form = $event->getForm();
                $order = $form->getData();

                if ('chooseExistingAddress' === $form->getClickedButton()->getName()) {
                    $address = $form->get('existingAddress')->getData();
                }

                if ('createNewAddress' === $form->getClickedButton()->getName()) {
                    $address = $form->get('newAddress')->getData();
                    $order->getCustomer()->addAddress($address);
                }

                $order->setShippingAddress($address);
            }
        );

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {

                $form = $event->getForm();
                $order = $form->getData();


            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OrderInterface::class,
        ));
    }
}
