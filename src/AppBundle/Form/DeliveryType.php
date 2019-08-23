<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot\Choice as TimeSlotChoice;
use AppBundle\Form\Entity\PackageWithQuantity;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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

class DeliveryType extends AbstractType
{
    protected $routing;
    protected $translator;
    protected $country;
    protected $locale;

    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator,
        string $country,
        string $locale)
    {
        $this->routing = $routing;
        $this->translator = $translator;
        $this->country = $country;
        $this->locale = $locale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('weight', NumberType::class, [
                'required' => false,
                'label' => 'form.delivery.weight.label'
            ])
            ->add('pickup', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.pickup.label',
                'constraints' => [
                    new Assert\Valid()
                ],
                'with_tags' => $options['with_tags']
            ])
            ->add('dropoff', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.dropoff.label',
                'constraints' => [
                    new Assert\Valid()
                ],
                'with_tags' => $options['with_tags']
            ])
            ;

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

        $builder->get('pickup')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $delivery = $event->getForm()->getParent()->getData();
                foreach ($delivery->getTasks() as $task) {
                    if ($task->getType() === Task::TYPE_PICKUP) {

                        if (null === $delivery->getId()) {
                            $before = new \DateTime();
                            while (($before->format('i') % 15) !== 0) {
                                $before->modify('+1 minute');
                            }
                            $task->setDoneBefore($before);
                        }

                        $event->setData($task);
                    }
                }
            }
        );

        $builder->get('dropoff')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $delivery = $event->getForm()->getParent()->getData();
                foreach ($delivery->getTasks() as $task) {
                    if ($task->getType() === Task::TYPE_DROPOFF) {

                        if (null === $delivery->getId()) {
                            $before = clone $delivery->getPickup()->getDoneBefore();
                            $before->modify('+1 hour');
                            $task->setDoneBefore($before);
                        }

                        $event->setData($task);
                    }
                }
            }
        );

        if ($options['with_store']) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

                $form = $event->getForm();
                $delivery = $event->getData();

                $isNew = $delivery->getId() === null;

                $form->add('store', EntityType::class, [
                    'class' => Store::class,
                    'query_builder' => function (EntityRepository $repository) {
                        return $repository->createQueryBuilder('s')
                            ->orderBy('s.name', 'ASC');
                    },
                    'label' => 'form.delivery.store.label',
                    'choice_label' => 'name',
                    'required' => false,
                    'disabled' => !$isNew
                ]);
            });
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $delivery = $event->getData();

            $store = $delivery->getStore();

            if (null === $store) {
                return;
            }

            if ($store->getPrefillPickupAddress()) {
                $defaultAddress = $store->getAddress();

                if ($defaultAddress) {
                    $addressForm = $form->get('pickup')->get('address');

                    $addressForm->get('id')->setData($defaultAddress->getId());
                    $addressForm->get('streetAddress')->setData($defaultAddress->getStreetAddress());
                }
            }

            if (null !== $store->getTimeSlot()) {

                $timeSlot = $store->getTimeSlot();
                $choicesWithDates = $timeSlot->getChoicesWithDates($this->country);

                $timeSlotOptions = [
                    'choices' => $choicesWithDates,
                    'choice_label' => [ $this, 'getTimeSlotChoiceLabel' ],
                    'choice_value' => function (TimeSlotChoiceWithDate $choiceWithDate = null) {
                        if (!$choiceWithDate) {
                            return '';
                        }

                        return sprintf('%s %s-%s',
                            $choiceWithDate->getDate()->format('Y-m-d'),
                            $choiceWithDate->getChoice()->getStartTime(),
                            $choiceWithDate->getChoice()->getEndTime()
                        );
                    },
                    'label' => 'form.delivery.time_slot.label',
                    'mapped' => false
                ];

                $form->get('pickup')->remove('doneAfter');
                $form->get('pickup')->remove('doneBefore');
                $form->get('pickup')->add('timeSlot', ChoiceType::class, $timeSlotOptions);

                $form->get('dropoff')->remove('doneAfter');
                $form->get('dropoff')->remove('doneBefore');
                $form->get('dropoff')->add('timeSlot', ChoiceType::class, $timeSlotOptions);
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
            'with_store' => true,
            'with_tags' => true,
        ));
    }

    private function getVehicleChoices()
    {
        return [
            $this->translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
            $this->translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
        ];
    }

    /**
     * Needs to be public to be used as callable.
     */
    public function getTimeSlotChoiceLabel(TimeSlotChoiceWithDate $choiceWithDate)
    {
        [ $start, $end ] = $choiceWithDate->getChoice()->toDateTime();
        $carbon = Carbon::instance($choiceWithDate->getDate());
        $calendar = $carbon->locale($this->locale)->calendar(null, [
            'sameDay' => '[' . $this->translator->trans('basics.today') . ']',
            'nextDay' => '[' . $this->translator->trans('basics.tomorrow') . ']',
            'nextWeek' => 'dddd',
        ]);

        return $this->translator->trans('time_slot.human_readable', [
            '%day%' => ucfirst(strtolower($calendar)),
            '%start%' => $start->format('H:i'),
            '%end%' => $end->format('H:i'),
        ]);
    }
}
