<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoopeatReturnsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $restaurant = $order->getRestaurant();

            if ($order->isEligibleToReusablePackaging()) {

                $supportsLoopEat = $restaurant->isLoopeatEnabled() && $restaurant->hasLoopEatCredentials();

                if (!$order->isMultiVendor() && $supportsLoopEat) {

                    $form->add('returns', HiddenType::class, [
                        'required' => false,
                        'mapped' => false,
                        'empty_data' => '[]'
                    ]);

                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Order::class,
        ));
    }
}

