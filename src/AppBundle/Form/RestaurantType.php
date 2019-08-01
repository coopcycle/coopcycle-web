<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantType extends LocalBusinessType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'localBusiness.form.description',
            ])
            ->add('orderingDelayDays', IntegerType::class, [
                'label' => 'localBusiness.form.orderingDelayDays',
                'mapped' => false
            ])
            ->add('orderingDelayHours', IntegerType::class, [
                'label' => 'localBusiness.form.orderingDelayHours',
                'mapped' => false
            ]);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('exclusive', CheckboxType::class, [
                    'label' => 'restaurant.form.exclusive.label',
                    'required' => false
                ])
                ->add('contract', ContractType::class)
                ->add('deliveryPerimeterExpression', HiddenType::class, [
                    'label' => 'localBusiness.form.deliveryPerimeterExpression'
                ])
                ->add('allowStripeConnect', CheckboxType::class, [
                    'label' => 'restaurant.form.allow_stripe_connect.label',
                    'mapped' => false,
                    'required' => false,
                ])
                ->add('quotesAllowed', CheckboxType::class, [
                    'label' => 'restaurant.form.quotes_allowed.label',
                    'required' => false,
                ])
                ->add('depositRefundEnabled', CheckboxType::class, [
                    'label' => 'restaurant.form.deposit_refund_enabled.label',
                    'required' => false,
                ])
                ->add('delete', SubmitType::class, [
                    'label' => 'basics.delete',
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $restaurant = $event->getData();
            $form = $event->getForm();
            $orderingDelayMinutes = $restaurant->getOrderingDelayMinutes();
            $orderingDelayDays = $orderingDelayMinutes / (60 * 24);
            $remainder = $orderingDelayMinutes % (60 * 24);
            $orderingDelayHours = $remainder / 60;

            $form->get('orderingDelayHours')->setData($orderingDelayHours);
            $form->get('orderingDelayDays')->setData($orderingDelayDays);

            if ($form->has('allowStripeConnect') && in_array('ROLE_RESTAURANT', $restaurant->getStripeConnectRoles())) {
                $form->get('allowStripeConnect')->setData(true);
            }
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $restaurant = $event->getForm()->getData();
                $orderingDelayDays = $event->getForm()->get('orderingDelayDays')->getData();
                $orderingDelayHours = $event->getForm()->get('orderingDelayHours')->getData();
                $restaurant->setOrderingDelayMinutes($orderingDelayDays * 60 * 24 + $orderingDelayHours * 60);

                if ($event->getForm()->has('allowStripeConnect')) {
                    $allowStripeConnect = $event->getForm()->get('allowStripeConnect')->getData();
                    if ($allowStripeConnect) {
                        $stripeConnectRoles = $restaurant->getStripeConnectRoles();
                        if (!in_array('ROLE_RESTAURANT', $stripeConnectRoles)) {
                            $stripeConnectRoles[] = 'ROLE_RESTAURANT';
                            $restaurant->setStripeConnectRoles($stripeConnectRoles);
                        }
                    }
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
