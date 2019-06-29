<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
    private $routing;
    private $translator;

    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator)
    {
        $this->routing = $routing;
        $this->translator = $translator;
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

            $vehicleChoices = [
                $this->translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
                $this->translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
            ];

            $builder->add('vehicle', ChoiceType::class, [
                'required' => true,
                'choices'  => $vehicleChoices,
                'placeholder' => 'form.delivery.vehicle.placeholder',
                'label' => 'form.delivery.vehicle.label',
                'multiple' => false,
                'expanded' => true,
            ]);
        }

        $builder->get('pickup')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
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
            function (FormEvent $event) use ($options) {
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
}
