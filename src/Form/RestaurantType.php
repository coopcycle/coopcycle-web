<?php

namespace AppBundle\Form;

use AppBundle\Entity\Cuisine;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Form\Restaurant\DabbaType;
use AppBundle\Form\Restaurant\FulfillmentMethodType;
use AppBundle\Form\Restaurant\LoopeatType;
use AppBundle\Form\Restaurant\ShippingOptionsTrait;
use AppBundle\Form\Restaurant\FulfillmentMethodsTrait;
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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantType extends LocalBusinessType
{
    use ShippingOptionsTrait, FulfillmentMethodsTrait {
        ShippingOptionsTrait::buildForm as buildShippingOptionsForm;
        FulfillmentMethodsTrait::buildForm as buildFulfillmentMethodsForm;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $this->buildShippingOptionsForm($builder, $options);
        $this->buildFulfillmentMethodsForm($builder, $options);

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
                    'block_prefix' => 'fulfillment_method_item',
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
                'help' => 'localBusiness.form.business_address.help',
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
                ->add('quotesAllowed', CheckboxType::class, [
                    'label' => 'restaurant.form.quotes_allowed.label',
                    'required' => false,
                ])
                ->add('depositRefundEnabled', CheckboxType::class, [
                    'label' => 'restaurant.form.deposit_refund_enabled.label',
                    'required' => false,
                ]);

            if ($this->cashOnDeliveryOptinEnabled) {
                $builder
                    ->add('cashOnDeliveryEnabled', CheckboxType::class, [
                        'label' => 'restaurant.form.cash_on_delivery_enabled.label',
                        'help' => 'restaurant.form.cash_on_delivery_enabled.help',
                        'required' => false,
                    ]);
            }
        }

        if ($options['loopeat_enabled']) {
            $builder->add('loopeat', LoopeatType::class, [
                'mapped' => false,
                'allow_toggle' => $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            ]);
        }

        if ($options['vytal_enabled']) {
            $builder->add('vytalEnabled', CheckboxType::class, [
                'label' => 'restaurant.form.vytal_enabled.label',
                'required' => false,
                'disabled' => !$this->authorizationChecker->isGranted('ROLE_ADMIN'),
            ]);
        }

        if ($options['en_boite_le_plat_enabled']) {
            $builder->add('enBoitLePlatEnabled', CheckboxType::class, [
                'label' => 'restaurant.form.en_boite_le_plat_enabled.label',
                'required' => false,
                'disabled' => !$this->authorizationChecker->isGranted('ROLE_ADMIN'),
            ]);
        }

        if ($options['dabba_enabled']) {
            $builder->add('dabba', DabbaType::class, [
                'mapped' => false,
                'allow_toggle' => $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $restaurant = $event->getData();
            $form = $event->getForm();

            if (null !== $restaurant->getId()) {

                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $gateway = $this->gatewayResolver->resolve();

                    switch ($gateway) {
                        case 'mercadopago':
                            $form->add('allowMercadopagoConnect', CheckboxType::class, [
                                'label' => 'restaurant.form.allow_mercadopago_connect.label',
                                'mapped' => false,
                                'required' => false,
                                'data' => in_array('ROLE_RESTAURANT', $restaurant->getMercadopagoConnectRoles())
                            ]);
                            break;
                        case 'stripe':
                        default:
                            $form->add('allowStripeConnect', CheckboxType::class, [
                                'label' => 'restaurant.form.allow_stripe_connect.label',
                                'mapped' => false,
                                'required' => false,
                                'data' => in_array('ROLE_RESTAURANT', $restaurant->getStripeConnectRoles())
                            ]);
                            break;
                    }
                    if (!$restaurant->isDeleted()) {
                        $form->add('delete', SubmitType::class, [
                            'label' => 'basics.delete',
                        ]);
                    }
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

                $isFoodEstablishment = FoodEstablishment::isValid($restaurant->getType());

                if ($isFoodEstablishment) {
                    $form
                        ->add('cuisines', HiddenType::class, [
                            'mapped' => false,
                            'required' => false,
                            'data' => $this->serializer->serialize($restaurant->getServesCuisine(), 'jsonld')
                        ]);

                    if ($options['edenred_enabled']) {
                        $form
                            ->add('edenredMerchantId', TextType::class, [
                                'label' => 'restaurant.form.edenred_merchant_id.label',
                                'help' => 'restaurant.form.edenred_merchant_id.help',
                                'required' => false,
                                'disabled' => !$this->authorizationChecker->isGranted('ROLE_ADMIN')
                            ]);
                    }
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

                if ($form->has('allowMercadopagoConnect')) {
                    $allowMercadopagoConnect = $form->get('allowMercadopagoConnect')->getData();
                    if ($allowMercadopagoConnect) {
                        $mercadopagoConnectRoles = $restaurant->getMercadopagoConnectRoles();
                        if (!in_array('ROLE_RESTAURANT', $mercadopagoConnectRoles)) {
                            $mercadopagoConnectRoles[] = 'ROLE_RESTAURANT';
                            $restaurant->setMercadopagoConnectRoles($mercadopagoConnectRoles);
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
            'loopeat_enabled' => false,
            'edenred_enabled' => false,
            'vytal_enabled' => false,
            'en_boite_le_plat_enabled' => false,
            'dabba_enabled' => false,
        ));
    }
}
