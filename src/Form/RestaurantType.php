<?php

namespace AppBundle\Form;

use AppBundle\Entity\Cuisine;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Form\Restaurant\FulfillmentMethodType;
use AppBundle\Form\Restaurant\LoopeatType;
use AppBundle\Form\Restaurant\ShippingOptionsTrait;
use AppBundle\Form\Type\LocalBusinessTypeChoiceType;
use Doctrine\Common\Collections\ArrayCollection;
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
    use ShippingOptionsTrait {
        buildForm as buildShippingOptionsForm;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $this->buildShippingOptionsForm($builder, $options);

        $builder
            ->add('type', LocalBusinessTypeChoiceType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'localBusiness.form.description',
                'help' => 'mardown_formatting.help',
                'attr' => ['rows' => '5']
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
            ->add('useDifferentBusinessAddress', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'localBusiness.form.use_different_business_address.label'
            ])
            ->add('businessAddress', AddressType::class, [
                'street_address_label' => 'localBusiness.form.business_address.label',
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
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
        }

        if ($options['loopeat_enabled']) {
            $builder->add('loopeat', LoopeatType::class, [
                'mapped' => false,
                'allow_toggle' => $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $restaurant = $event->getData();
            $form = $event->getForm();

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

            if (null !== $restaurant->getId()) {

                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $form->add('allowStripeConnect', CheckboxType::class, [
                        'label' => 'restaurant.form.allow_stripe_connect.label',
                        'mapped' => false,
                        'required' => false,
                        'data' => in_array('ROLE_RESTAURANT', $restaurant->getStripeConnectRoles())
                    ]);
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

                $isFoodEstablishment = false;
                foreach (FoodEstablishment::values() as $value) {
                    if ($value->getValue() === $restaurant->getType()) {
                        $isFoodEstablishment = true;
                        break;
                    }
                }

                if ($isFoodEstablishment) {
                    $form
                        ->add('cuisines', HiddenType::class, [
                            'mapped' => false,
                            'required' => false,
                            'data' => $this->serializer->serialize($restaurant->getServesCuisine(), 'jsonld')
                        ]);
                }
            }

            if ($restaurant->hasDifferentBusinessAddress()) {
                $form->get('useDifferentBusinessAddress')->setData(true);
            }
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {

                $form = $event->getForm();
                $restaurant = $form->getData();

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

                if ($form->has('cuisines')) {

                    $cuisineRepository = $this->entityManager->getRepository(Cuisine::class);

                    $originalCuisines = new ArrayCollection();
                    foreach ($restaurant->getServesCuisine() as $c) {
                        $originalCuisines->add($c);
                    }

                    $cuisines = $form->get('cuisines')->getData();
                    $cuisines = json_decode($cuisines, true);

                    $newCuisines = new ArrayCollection();
                    foreach ($cuisines as $c) {
                        if ($cuisine = $cuisineRepository->find($c['id'])) {
                            $newCuisines->add($cuisine);
                        }
                    }

                    foreach ($originalCuisines as $c) {
                        if (!$newCuisines->contains($c)) {
                            $restaurant->removeServesCuisine($c);
                        }
                    }
                    foreach ($newCuisines as $c) {
                        $restaurant->addServesCuisine($c);
                    }
                }

                $useDifferentBusinessAddress =
                    $event->getForm()->get('useDifferentBusinessAddress')->getData();

                if (!$useDifferentBusinessAddress) {
                    $restaurant->setBusinessAddress(null);
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
