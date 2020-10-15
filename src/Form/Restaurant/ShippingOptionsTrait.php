<?php

namespace AppBundle\Form\Restaurant;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

trait ShippingOptionsTrait
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('orderingDelayDays', IntegerType::class, [
                'label' => 'localBusiness.form.orderingDelayDays',
                'mapped' => false
            ])
            ->add('shippingOptionsDays', IntegerType::class, [
                'label' => 'localBusiness.form.shippingOptionsDays',
                'attr' => [
                    'min' => 1,
                    'max' => 6
                ]
            ])
            ->add('orderingDelayHours', IntegerType::class, [
                'label' => 'localBusiness.form.orderingDelayHours',
                'mapped' => false
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $restaurant = $event->getData();
            $form = $event->getForm();

            $orderingDelayMinutes = $restaurant->getOrderingDelayMinutes();
            $orderingDelayDays = $orderingDelayMinutes / (60 * 24);
            $remainder = $orderingDelayMinutes % (60 * 24);
            $orderingDelayHours = $remainder / 60;

            $form->get('orderingDelayHours')->setData($orderingDelayHours);
            $form->get('orderingDelayDays')->setData($orderingDelayDays);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $restaurant = $form->getData();

            $orderingDelayDays = $form->get('orderingDelayDays')->getData();
            $orderingDelayHours = $form->get('orderingDelayHours')->getData();
            $restaurant->setOrderingDelayMinutes(
                ($orderingDelayDays * 60 * 24) + ($orderingDelayHours * 60)
            );
        });
    }
}
