<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Form\Entity\PackageWithQuantity;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\TimeRange;
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
use Symfony\Component\Translation\TranslatorInterface;
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
        $builder
            ->add('weight', NumberType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'form.delivery.weight.label',
                'help' => 'form.delivery.weight.help'
            ]);

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
                'with_recipient_details' => $options['with_dropoff_recipient_details'],
            ]);

            if (null === $store) {
                return;
            }

            if (null !== $store->getTimeSlot()) {

                $timeSlotOptions = [
                    'time_slot' => $store->getTimeSlot(),
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
            }

            if (null !== $store->getPackageSet()) {

                $packageSet = $store->getPackageSet();

                $data = [];

                if ($delivery->hasPackages()) {
                    foreach ($delivery->getPackages() as $deliveryPackage) {
                        $pwq = new PackageWithQuantity($deliveryPackage->getPackage());
                        $pwq->setQuantity($deliveryPackage->getQuantity());
                        $data[] = $pwq;
                    }
                }

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
                ]);

                $form->get('packages')->setData($data);
            }
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

            $coordinates = [];
            foreach ($delivery->getTasks() as $task) {
                $coordinates[] = $task->getAddress()->getGeo();
            }

            $data = $this->routing->getServiceResponse('route', $coordinates, [
                'steps' => 'true',
                'overview' => 'full'
            ]);

            $distance = $data['routes'][0]['distance'];
            $duration = $data['routes'][0]['duration'];
            $polyline = $data['routes'][0]['geometry'];

            $delivery->setDistance((int) $distance);
            $delivery->setDuration((int) $duration);
            $delivery->setPolyline($polyline);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Delivery::class,
            'with_vehicle' => false,
            'with_tags' => $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            'with_dropoff_recipient_details' => false,
        ));
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
