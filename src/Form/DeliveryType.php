<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class DeliveryType extends AbstractType
{
    public function __construct(
        protected RoutingInterface $routing,
        protected TranslatorInterface $translator,
        protected string $country,
        protected string $locale,
    )
    { }

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

            $isNew = null === $delivery->getId();

            if ($isNew && true === $options['asap_timing']) {

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
                    'with_time_slot' => $this->getTimeSlot($options),
                    'with_package_set' => $this->getPackageSet($options),
                    'with_packages_required' => true,
                    'with_weight' => $options['with_weight'],
                    'with_weight_required' => true,
                ],
                'prototype_data' => new Task(),
            ]);
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

    private function getTimeSlot(array $options): ?TimeSlot
    {
        return $options['with_time_slot'];
    }

    private function getPackageSet(array $options): ?PackageSet
    {
        return $options['with_package_set'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Delivery::class,
            'with_vehicle' => false,
            'with_weight' => true,
            'with_time_slot' => null,
            'with_package_set' => null,
            'asap_timing' => false,
        ]);

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
