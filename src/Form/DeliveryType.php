<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DeliveryType extends AbstractType
{

    public function __construct(
        protected RoutingInterface $routing,
        protected TranslatorInterface $translator,
        protected AuthorizationCheckerInterface $authorizationChecker,
        protected string $country,
        protected string $locale,
        private readonly OrderManager $orderManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (true === $options['with_vehicle']) {
            $builder->add('vehicle', ChoiceType::class, [
                'required' => true,
                'choices'  => $this->getVehicleChoices(),
                'placeholder' => 'form.delivery.vehicle.placeholder',
                'label' => 'form.delivery.vehicle.label',
                'multiple' => false,
                'expanded' => true,
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $delivery = $event->getData();

            $store = $delivery->getStore();

            // When this is a new delivery,
            // set defaults for pickup/dropoff date
            if (null === $delivery->getId()) {

                $now = Carbon::now();

                $pickupBefore = clone $now;
                while (($pickupBefore->format('i') % 15) !== 0) {
                    $pickupBefore->modify('+1 minute');
                }

                $dropoffBefore = clone $pickupBefore;
                $dropoffBefore->modify('+1 hour');

                $pickupAfter = clone $pickupBefore;
                $pickupAfter->modify('-15 minute');

                $dropoffAfter = clone $dropoffBefore;
                $dropoffAfter->modify('-15 minute');

                $delivery->getPickup()->setDoneBefore($pickupBefore);
                $delivery->getPickup()->setDoneAfter($pickupAfter);
                $delivery->getDropoff()->setDoneAfter($dropoffAfter);
                $delivery->getDropoff()->setDoneBefore($dropoffBefore);
            }

            $form->add('tasks', CollectionType::class, [
                'entry_type' => TaskType::class,
                'entry_options' => [
                    'constraints' => [
                        new Assert\Valid()
                    ],
                    'with_tags' => $options['with_tags'],
                    'with_addresses' => null !== $store ? $store->getAddresses() : [],
                    'with_remember_address' => $options['with_remember_address'],
                    'with_time_slot' => $this->getTimeSlot($options, $store),
                    'with_time_slots' => $this->getTimeSlots($options, $store),
                    'with_doorstep' => $options['with_dropoff_doorstep'],
                    'with_address_props' => $options['with_address_props'],
                    'with_package_set' => $this->getPackageSet($options, $store),
                    'with_packages_required' => null !== $store ? $store->isPackagesRequired() : true,
                    'with_weight' => $options['with_weight'],
                    'with_weight_required' => null !== $store ? $store->isWeightRequired() : true,
                    'with_position' => true,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype_data' => new Task(),
            ]);

            $isMultiDropEnabled = null !== $store ? $store->isMultiDropEnabled() : false;
            // customers/stores owners are not allowed to edit existing deliveries
            $isEditEnabled = $this->authorizationChecker->isGranted('ROLE_DISPATCHER') || is_null($delivery->getId());

            if ($isMultiDropEnabled && $isEditEnabled) {
                $form->add('addTask', ButtonType::class, [
                    'label' => 'basics.add',
                    'attr' => [
                        'data-add' => 'dropoff'
                    ],
                ]);
            }

            // Allow admins to define an arbitrary price
            if (true === $options['with_arbitrary_price'] &&
                $this->authorizationChecker->isGranted('ROLE_ADMIN')) {

                $arbitraryPrice = $options['arbitrary_price'];

                $form->add('arbitraryPrice', CheckboxType::class, [
                    'label' => 'form.delivery.arbitrary_price.label',
                    'mapped' => false,
                    'required' => false,
                    'data' => isset($arbitraryPrice),
                ])
                ->add('variantName', TextType::class, [
                    'label' => 'form.new_order.variant_name.label',
                    'help' => 'form.new_order.variant_name.help',
                    'mapped' => false,
                    'required' => false,
                    'data' => $arbitraryPrice ? $arbitraryPrice->getVariantName() : null,
                ])
                ->add('variantPrice', MoneyType::class, [
                    'label' => 'form.new_order.variant_price.label',
                    'mapped' => false,
                    'required' => false,
                    'data' => $arbitraryPrice ? $arbitraryPrice->getValue() : null,
                ]);
            }

            $isDeliveryOrder = null !== $store && $store->getCreateOrders();
            
            if ($options['with_bookmark'] && $isDeliveryOrder && $this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                $form->add('bookmark', CheckboxType::class, [
                    'label' => 'form.delivery.bookmark.label',
                    'mapped' => false,
                    'required' => false,
                    'data' => $delivery->getOrder() && $this->orderManager->hasBookmark($delivery->getOrder()),
                ]);
            }

            if ($options['with_recurrence'] && $isDeliveryOrder && $this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                $form->add('recurrence', HiddenType::class, [
                    'required' => false,
                    'mapped' => false,
                ]);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $data = $event->getData();

            $tasks = $data['tasks'];

            $reordered = [];

            foreach ($tasks as $task) {
                if (isset($task['position']) && is_numeric($task['position'])) {
                    $reordered[(int) $task['position']] = $task;
                } else {
                    $reordered[] = $task;
                }
            }

            $data['tasks'] = $reordered;

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            if (!$form->isValid()) {
                return;
            }

            $delivery = $event->getForm()->getData();

            $coordinates = [];
            foreach ($delivery->getTasks() as $task) {
                if ($address = $task->getAddress()) {
                    $coordinates[] = $address->getGeo();
                }
            }

            if (count($coordinates) > 0) {
                $delivery->setDistance($this->routing->getDistance(...$coordinates));
                $delivery->setDuration($this->routing->getDuration(...$coordinates));
                $delivery->setPolyline($this->routing->getPolyline(...$coordinates));
            }
        });
    }

    /**
     * @return TimeSlot|null
     */
    private function getTimeSlot(array $options, ?Store $store = null): ?TimeSlot
    {
        /*
        // See https://github.com/coopcycle/coopcycle-web/issues/3465
        // For admin users we do not show timeslots dropdown, now we show a date picker so they can select a free range
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) { //check if user is administrator
            return null;
        }
        */

        if (null !== $options['with_time_slot']) {

            return $options['with_time_slot'];
        }

        if ($store) {

            return $store->getTimeSlot();
        }

        return null;
    }

    /**
     * @return TimeSlot[]|null
     */
    private function getTimeSlots(array $options, ?Store $store = null)
    {
        /*
        // See https://github.com/coopcycle/coopcycle-web/issues/3465
        // For admin users we do not show timeslots dropdown, now we show a date picker so they can select a free range
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) { //check if user is administrator
            return null;
        }
        */

        if (null !== $options['with_time_slots']) {

            return $options['with_time_slots'];
        }

        if ($store) {

            return $store->getTimeSlots();
        }

        return null;
    }

    /**
     * @return PackageSet|null
     */
    private function getPackageSet(array $options, ?Store $store = null): ?PackageSet
    {
        if (null !== $options['with_package_set']) {

            return $options['with_package_set'];
        }

        if ($store) {

            return $store->getPackageSet();
        }

        return null;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Delivery::class,
            'with_vehicle' => false,
            'with_weight' => true,
            'with_tags' => $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            'with_dropoff_doorstep' => false,
            'with_time_slot' => null,
            'with_time_slots' => null,
            'with_package_set' => null,
            'with_remember_address' => false,
            'with_address_props' => false,
            'with_arbitrary_price' => false,
            'arbitrary_price' => null,
            'with_bookmark' => false,
            'with_recurrence' => false,
        ));

        $resolver->setAllowedTypes('with_time_slot', ['null', TimeSlot::class]);
        $resolver->setAllowedTypes('with_package_set', ['null', PackageSet::class]);
    }

    private function getVehicleChoices()
    {
        return [
            $this->translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
            $this->translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
        ];
    }

    public function getBlockPrefix()
    {
        return 'delivery';
    }
}
