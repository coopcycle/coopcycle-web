<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\FailureReasonSet;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\Urbantz\Hub as UrbantzHub;
use AppBundle\Entity\User;
use AppBundle\Form\Type\QueryBuilder\OrderByNameQueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;


class StoreType extends LocalBusinessType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                    'required' => false,
                ))
                ->add('packageSet', EntityType::class, array(
                    'label' => 'form.store_type.package_set.label',
                    'class' => PackageSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                    'required' => false,
                ))
                ->add('prefillPickupAddress', CheckboxType::class, [
                    'label' => 'form.store_type.prefill_pickup_address.label',
                    'required' => false,
                ])
                ->add('weightRequired', CheckboxType::class, [
                    'label' => 'form.store_type.weight_required.label',
                    'required' => false,
                ])
                ->add('packagesRequired', CheckboxType::class, [
                    'label' => 'form.store_type.packages_required.label',
                    'required' => false,
                ])
                ->add('multiDropEnabled', CheckboxType::class, [
                    'label' => 'form.store_type.multi_drop_enabled.label',
                    'help' => 'form.store_type.multi_drop_enabled.help',
                    'required' => false,
                ])
                ->add('multiPickupEnabled', CheckboxType::class, [
                    'label' => 'form.store_type.multi_pickup_enabled.label',
                    'help' => 'form.store_type.multi_pickup_enabled.help',
                    'required' => false,
                ])
                ->add('tags', TagsType::class)
                ->add('failureReasonSet', EntityType::class, array(
                    'label' => 'form.store_type.failure_reason_set.label',
                    'help' => 'form.store_type.failure_reason_set.help',
                    'class' => FailureReasonSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                    'required' => false,
                    'translation_domain' => 'messages',
                    'help_translation_parameters' => [
                        '%failure_reason_set%' => $this->urlGenerator->generate('admin_failures_list', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        '%entity%' => 'store',
                    ],
                    'help_html' => true,

                ))
                ->add('checkExpression', HiddenType::class, [
                    'label' => 'form.store.check_expression'
                ])
                ->add('document', HiddenType::class, [
                    'label' => 'form.store.document'
                ]);

            if ($this->cykeEnabled) {
                $builder
                    ->add('cykeUserEmail', TextType::class, [
                        'label' => 'form.store.cyke_user_email',
                        'required' => false,
                    ])
                    // Rendered as password-masked in the template (see store/_partials/cyke.html.twig):
                    // PasswordType always clears its value on a non-submitted render
                    // (regardless of always_empty), which isn't what we want here — this
                    // is a token to review/copy, not a login password typed once.
                    ->add('cykeUserToken', TextType::class, [
                        'label' => 'form.store.cyke_user_token',
                        'required' => false,
                    ])
                    // Populated client-side from the Cyke API (see store-form.js):
                    // the list of package types depends on credentials that may not
                    // be persisted yet, so it can't be resolved as a normal ChoiceType.
                    ->add('cykePackageTypeId', HiddenType::class, [
                        'label' => 'form.store.cyke_package_type_id.label',
                        'help' => 'form.store.cyke_package_type_id.help',
                        'required' => false,
                    ])
                    // Cyke validates the delivery slot we send against the store's
                    // configured opening hours in its own UI (which may close earlier
                    // on some days). EDIFACT-imported deliveries (see
                    // SyncTransportersCommand) carry no time info, so we compute a slot
                    // that fits these per-day opening hours, expressed in schema.org's
                    // openingHours format, e.g. "Mo-Fr 09:00-18:00, Sa 09:00-16:00".
                    ->add('cykeTimeSlot', TextType::class, [
                        'label' => 'form.store.cyke_time_slot.label',
                        'help' => 'form.store.cyke_time_slot.help',
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'form.store.cyke_time_slot.placeholder',
                        ],
                        'constraints' => [
                            new Assert\Regex([
                                // One or more schema.org openingHours entries, e.g.
                                // "Mo-Fr 09:00-18:00, Sa 09:00-16:00".
                                'pattern' => '/^(?:(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:[,-](?:Mo|Tu|We|Th|Fr|Sa|Su))*\s+([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d)(?:\s*,\s*(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:[,-](?:Mo|Tu|We|Th|Fr|Sa|Su))*\s+([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d)*$/',
                                'message' => 'form.store.cyke_time_slot.invalid',
                            ]),
                        ],
                    ]);
                // cykeWebhookSecret is intentionally *not* a form field: it's generated
                // server-side (see the POST_SET_DATA listener below) and only ever
                // displayed read-only, since Cyke itself has no way to hand us a secret
                // for the webhook it calls back into us with.
            }

            if ($this->cashOnDeliveryOptinEnabled) {
                $builder
                    ->add('cashOnDeliveryEnabled', CheckboxType::class, [
                        'label' => 'store.form.cash_on_delivery_enabled.label',
                        'help' => 'store.form.cash_on_delivery_enabled.help',
                        'required' => false,
                    ]);
            }

            if ($this->transportersEnabled) {
                $transporterConfig = $this->transportersConfig;
                $choices = array_reduce(array_keys($this->transportersConfig), function ($acc, $transporter) use (&$transporterConfig) {
                    if ($transporterConfig[$transporter]['enabled'] ?? false) {
                        $acc[$transporterConfig[$transporter]['name']] = $transporter;
                    }
                    return $acc;
                });

                $builder->add('transporter', ChoiceType::class, [
                    'label' => 'This store is managed by the transporter',
                    'help' => 'Select a transporter to manage this store',
                    'choices' => $choices,
                    'required' => false,
                ]);
            }

            if ($this->rdcEnabled && !empty($this->rdcConnections)) {
                $choices = array_reduce(array_keys($this->rdcConnections), function ($acc, $key) {
                    $acc[$this->rdcConnections[$key]['name'] ?? $key] = $key;
                    return $acc;
                });

                $builder->add('rdcConnectionId', ChoiceType::class, [
                    'label' => 'RDC Connection',
                    'help' => 'Select an RDC connection for this store',
                    'choices' => $choices,
                    'required' => false,
                ]);
            }
        }

        //TODO(r0xsh): add check if StandTrack is enabled
        if ($this->standtrackEnabled) {
            $builder->add('storeGLN', TextType::class, [
                'required' => false,
                'label' => 'store.form.storeGLN.label',
                'help' => 'store.form.storeGLN.help',
                'help_html' => true
            ]);
        }


        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $store = $event->getData();

            if (null !== $store && null !== $store->getId()) {
                // Remove default address form
                $form->remove('address');

                if (!$store->isDeleted()) {
                    $form->add('delete', SubmitType::class, [
                        'label' => 'basics.delete',
                    ]);
                }
            }

            $data = $this->userManager->findUsersByRole('ROLE_COURIER');

            $form->add('defaultCourier', EntityType::class, [
                'class' => User::class,
                'choices' => $data,
                'label' => 'form.store_type.defaultCourier.label',
                'choice_label' => 'username',
                'choice_value' => 'username',
                'placeholder' => 'form.store_type.defaultCourier.placeholder',
                'help' => 'form.store_type.defaultCourier.help',
                'required' => false,
            ]);

            if (null !== $store && null !== $store->getId()) {
                $urbantzHub = $this->entityManager->getRepository(UrbantzHub::class)->findOneBy(['store' => $store]);
                $form->add('urbantzHubId', TextType::class, [
                    'label' => 'form.store.urbantz_hub_id',
                    'mapped' => false,
                    'data' => null !== $urbantzHub ? $urbantzHub->getHub() : '',
                    'required' => false,
                ]);
            }

            // The webhook secret is generated by us, once, the first time the Cyke tab
            // is displayed for a store — Cyke has no way to hand us one, and it's the
            // admin who pastes it into Cyke's own webhook configuration UI.
            if (null !== $store && null !== $store->getId() && $this->cykeEnabled
                && empty($store->getCykeWebhookSecret())) {
                $store->setCykeWebhookSecret(bin2hex(random_bytes(32)));
                $this->entityManager->flush();
            }

            // Default to Cyke's own "Journée entière" fixed slot, so stores that
            // never touch this setting still send a slot Cyke will accept.
            if (null !== $store && null !== $store->getId() && $this->cykeEnabled
                && empty($store->getCykeTimeSlot())) {
                $store->setCykeTimeSlot('08:00-18:00');
                $this->entityManager->flush();
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if (null === $store->getPackageSet()) {
                $store->setPackagesRequired(false);
            }

            if (null === $store->getId()) {
                $defaultAddress = $store->getAddress();
                $store->addAddress($defaultAddress);
            }

            if ($form->has('urbantzHubId')) {

                $hub = $form->get('urbantzHubId')->getData();

                $urbantzHub = $this->entityManager->getRepository(UrbantzHub::class)->findOneBy(['store' => $store]);

                if (empty($hub)) {
                    if (null !== $urbantzHub) {
                        $this->entityManager->remove($urbantzHub);
                    }
                } else {
                    if (null === $urbantzHub) {
                        $urbantzHub = new UrbantzHub();
                        $urbantzHub->setStore($store);
                    }
                    $urbantzHub->setHub($hub);
                    $this->entityManager->persist($urbantzHub);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
