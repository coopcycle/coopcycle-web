<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Restaurant\FulfillmentMethodType;
use AppBundle\Form\Type\LocalBusinessTypeChoiceType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
            ->add('type', LocalBusinessTypeChoiceType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'localBusiness.form.description',
            ])
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
            ])
            ->add('fulfillmentMethods', CollectionType::class, [
                'entry_type' => FulfillmentMethodType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => false,
                'allow_delete' => false,
                'prototype' => false,
            ])
            ;

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('featured', CheckboxType::class, [
                    'label' => 'restaurant.form.featured.label',
                    'required' => false
                ])
                ->add('exclusive', CheckboxType::class, [
                    'label' => 'restaurant.form.exclusive.label',
                    'required' => false
                ])
                ->add('contract', ContractType::class)
                ->add('deliveryPerimeterExpression', HiddenType::class, [
                    'label' => 'localBusiness.form.deliveryPerimeterExpression'
                ])
                ->add('quotesAllowed', CheckboxType::class, [
                    'label' => 'restaurant.form.quotes_allowed.label',
                    'required' => false,
                ])
                ->add('depositRefundEnabled', CheckboxType::class, [
                    'label' => 'restaurant.form.deposit_refund_enabled.label',
                    'required' => false,
                ])
                ->add('enabledFulfillmentMethods', ChoiceType::class, [
                    'choices'  => [
                        'fulfillment_method.delivery' => 'delivery',
                        'fulfillment_method.collection' => 'collection',
                    ],
                    'label' => 'restaurant.form.fulfillment_methods.label',
                    'required' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'mapped' => false,
                ])
                ->add('delete', SubmitType::class, [
                    'label' => 'basics.delete',
                ]);

            if ($options['loopeat_enabled']) {
                $builder->add('loopeatEnabled', CheckboxType::class, [
                    'label' => 'restaurant.form.loopeat_enabled.label',
                    'required' => false,
                ]);
            }
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $restaurant = $event->getData();
            $form = $event->getForm();

            $orderingDelayMinutes = $restaurant->getOrderingDelayMinutes();
            $orderingDelayDays = $orderingDelayMinutes / (60 * 24);
            $remainder = $orderingDelayMinutes % (60 * 24);
            $orderingDelayHours = $remainder / 60;

            $form->get('orderingDelayHours')->setData($orderingDelayHours);
            $form->get('orderingDelayDays')->setData($orderingDelayDays);

            if ($form->has('enabledFulfillmentMethods')) {

                $enabledFulfillmentMethods = [];
                if ($restaurant->isFulfillmentMethodEnabled('delivery')) {
                    $enabledFulfillmentMethods[] = 'delivery';
                }
                if ($restaurant->isFulfillmentMethodEnabled('collection')) {
                    $enabledFulfillmentMethods[] = 'collection';
                }

                $form->get('enabledFulfillmentMethods')->setData($enabledFulfillmentMethods);
            }

            if ($options['loopeat_enabled'] && $restaurant->hasLoopEatCredentials()) {
                $form
                    ->add('loopeatDisconnect', SubmitType::class, [
                        'label' => 'restaurant.form.loopeat_disconnect.label',
                    ]);
            }

            if (null !== $restaurant->getId()) {

                $form->add('allowStripeConnect', CheckboxType::class, [
                    'label' => 'restaurant.form.allow_stripe_connect.label',
                    'mapped' => false,
                    'required' => false,
                ]);
                if ($form->has('allowStripeConnect') && in_array('ROLE_RESTAURANT', $restaurant->getStripeConnectRoles())) {
                    $form->get('allowStripeConnect')->setData(true);
                }

                if ($this->authorizationChecker->isGranted('ROLE_ADMIN') && ($this->debug || 'de' === $this->country)) {
                    $form
                        ->add('enableGiropay', CheckboxType::class, [
                            'label' => 'restaurant.form.giropay_enabled.label',
                            'mapped' => false,
                            'required' => false,
                            'data' => $restaurant->isStripePaymentMethodEnabled('giropay'),
                        ]);
                }
            }
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {

                $form = $event->getForm();
                $restaurant = $form->getData();

                $orderingDelayDays = $form->get('orderingDelayDays')->getData();
                $orderingDelayHours = $form->get('orderingDelayHours')->getData();
                $restaurant->setOrderingDelayMinutes(
                    ($orderingDelayDays * 60 * 24) + ($orderingDelayHours * 60)
                );

                if ($form->has('enabledFulfillmentMethods')) {
                    $enabledFulfillmentMethods = $form->get('enabledFulfillmentMethods')->getData();

                    $restaurant->addFulfillmentMethod('delivery', in_array('delivery', $enabledFulfillmentMethods));
                    $restaurant->addFulfillmentMethod('collection', in_array('collection', $enabledFulfillmentMethods));
                }

                if ($form->has('allowStripeConnect')) {
                    $allowStripeConnect = $form->get('allowStripeConnect')->getData();
                    if ($allowStripeConnect) {
                        $stripeConnectRoles = $restaurant->getStripeConnectRoles();
                        if (!in_array('ROLE_RESTAURANT', $stripeConnectRoles)) {
                            $stripeConnectRoles[] = 'ROLE_RESTAURANT';
                            $restaurant->setStripeConnectRoles($stripeConnectRoles);
                        }
                    }
                }

                if ($form->has('enableGiropay')) {
                    $enableGiropay = $form->get('enableGiropay')->getData();
                    if ($enableGiropay) {
                        $restaurant->enableStripePaymentMethod('giropay');
                    } else {
                        $restaurant->disableStripePaymentMethod('giropay');
                    }
                }

                if ($form->getClickedButton() && 'loopeatDisconnect' === $form->getClickedButton()->getName()) {
                    $restaurant->clearLoopEatCredentials();
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => LocalBusiness::class,
            'loopeat_enabled' => $this->loopeatEnabled,
        ));
    }
}
