<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Entity\PackageWithQuantity;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DeliveryType extends AbstractType
{
    protected $routing;
    protected $translator;
    protected $authorizationChecker;
    protected $country;
    protected $locale;

    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator,
        AuthorizationCheckerInterface $authorizationChecker,
        string $country,
        string $locale)
    {
        $this->routing = $routing;
        $this->translator = $translator;
        $this->authorizationChecker = $authorizationChecker;
        $this->country = $country;
        $this->locale = $locale;
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

                $delivery->getPickup()->setDoneBefore($pickupBefore);
                $delivery->getDropoff()->setDoneBefore($dropoffBefore);
            }

            $form->add('pickup', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.pickup.label',
                'constraints' => [
                    new Assert\Valid()
                ],
                'data' => $delivery->getPickup(),
                'with_tags' => $options['with_tags'],
                'with_addresses' => null !== $store ? $store->getAddresses() : [],
                'address_placeholder' => 'form.delivery.pickup.address_placeholder',
                'street_address_label' => 'form.delivery.pickup.label',
                'with_remember_address' => $options['with_remember_address'],
            ]);
            $form->add('dropoff', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.dropoff.label',
                'constraints' => [
                    new Assert\Valid()
                ],
                'data' => $delivery->getDropoff(),
                'with_tags' => $options['with_tags'],
                'with_addresses' => null !== $store ? $store->getAddresses() : [],
                'address_placeholder' => 'form.delivery.dropoff.address_placeholder',
                'street_address_label' => 'form.delivery.dropoff.label',
                'with_recipient_details' => $options['with_dropoff_recipient_details'],
                'with_doorstep' => $options['with_dropoff_doorstep'],
                'with_remember_address' => $options['with_remember_address'],
            ]);
        });

        // Add weight field if needed
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $delivery = $event->getData();
            $store = $delivery->getStore();

            if (true === $options['with_weight']) {

                $required = null !== $store && $store->isWeightRequired();

                $form
                    ->add('weight', NumberType::class, [
                        'required' => $required,
                        'html5' => true,
                        'label' => 'form.delivery.weight.label',
                    ]);

                if (null !== $delivery->getId() && null !== $delivery->getWeight()) {
                    $form->get('weight')->setData($delivery->getWeight() / 1000);
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $delivery = $event->getData();

            if (!$timeSlot = $this->getTimeSlot($options, $delivery->getStore())) {
                return;
            }

            $timeSlotOptions = [
                'time_slot' => $timeSlot,
                'label' => 'form.delivery.time_slot.label',
                'mapped' => false
            ];

            $pickupTimeSlotOptions = $dropoffTimeSlotOptions = $timeSlotOptions;

            if (null !== $delivery->getId()) {

                $pickupTimeSlotOptions['disabled'] = true;
                $pickupTimeSlotOptions['data'] = TimeSlotChoice::fromTask($delivery->getPickup());

                $dropoffTimeSlotOptions['disabled'] = true;
                $dropoffTimeSlotOptions['data'] = TimeSlotChoice::fromTask($delivery->getDropoff());
            }

            $form->get('pickup')->remove('doneAfter');
            $form->get('pickup')->remove('doneBefore');
            $form->get('pickup')->add('timeSlot', TimeSlotChoiceType::class, $pickupTimeSlotOptions);

            $form->get('dropoff')->remove('doneAfter');
            $form->get('dropoff')->remove('doneBefore');
            $form->get('dropoff')->add('timeSlot', TimeSlotChoiceType::class, $dropoffTimeSlotOptions);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $delivery = $event->getData();

            if (!$packageSet = $this->getPackageSet($options, $delivery->getStore())) {
                return;
            }

            $data = [];

            if ($delivery->hasPackages()) {
                foreach ($delivery->getPackages() as $deliveryPackage) {
                    $pwq = new PackageWithQuantity($deliveryPackage->getPackage());
                    $pwq->setQuantity($deliveryPackage->getQuantity());
                    $data[] = $pwq;
                }
            }

            $store = $delivery->getStore();
            $isPackagesRequired = null !== $store ? $store->isPackagesRequired() : true;

            $form->add('packages', CollectionType::class, [
                'entry_type' => PackageWithQuantityType::class,
                'entry_options' => [
                    'label' => false,
                    'package_set' => $packageSet
                ],
                'label' => 'form.delivery.packages.label',
                'mapped' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'data-packages-required' => var_export($isPackagesRequired, true),
                ]
            ]);

            $form->get('packages')->setData($data);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $delivery = $event->getData();

            if ($form->has('packages')) {
                $packages = $form->get('packages')->getData();
                foreach ($packages as $packageWithQuantity) {
                    if ($packageWithQuantity->getQuantity() > 0) {
                        $delivery->addPackageWithQuantity(
                            $packageWithQuantity->getPackage(),
                            $packageWithQuantity->getQuantity()
                        );
                    }
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            if (!$form->isValid()) {
                return;
            }

            $delivery = $event->getForm()->getData();

            if ($form->has('weight')) {
                $weightK = $form->get('weight')->getData();
                $weight = $weightK * 1000;
                $delivery->setWeight($weight);
            }

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
        if (null !== $options['with_time_slot']) {

            return $options['with_time_slot'];
        }

        if ($store) {

            return $store->getTimeSlot();
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
            'with_dropoff_recipient_details' => false,
            'with_dropoff_doorstep' => false,
            'with_time_slot' => null,
            'with_package_set' => null,
            'with_remember_address' => false,
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
