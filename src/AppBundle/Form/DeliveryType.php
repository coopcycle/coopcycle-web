<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class DeliveryType extends AbstractType
{
    private $doctrine;
    private $routing;
    private $translator;

    public function __construct(
        ManagerRegistry $doctrine,
        RoutingInterface $routing,
        TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->routing = $routing;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $vehicleChoices = [
            $this->translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
            $this->translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
        ];

        $builder
            ->add('weight', NumberType::class, [
                'required' => false,
                'label' => 'form.delivery.weight.label'
            ])
            ->add('pickup', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.pickup.label'
            ])
            ->add('dropoff', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.dropoff.label'
            ])
            ->add('vehicle', ChoiceType::class, [
                'required' => true,
                'choices'  => $vehicleChoices,
                'placeholder' => 'form.delivery.vehicle.placeholder',
                'label' => 'form.delivery.vehicle.label',
                'multiple' => false,
                'expanded' => true,
            ]);

        if ($options['pricing_rule_set']) {

            $transformer = new CallbackTransformer(
                function ($entity) {
                    if ($entity instanceof PricingRuleSet) {
                        return $entity->getId();
                    }
                    return '';
                },
                function ($id) {
                    if (!$id) {
                        return null;
                    }
                    return $this->doctrine->getRepository(PricingRuleSet::class)->find($id);
                }
            );

            $builder
                ->add('pricingRuleSet', HiddenType::class, array(
                    'mapped' => false,
                ));
            $builder->get('pricingRuleSet')
                ->addViewTransformer($transformer);

        } else {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'mapped' => false,
                    'required' => true,
                    'placeholder' => 'form.store_type.pricing_rule_set.placeholder',
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                    }
                ));
        }

        $builder->get('pickup')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
                $delivery = $event->getForm()->getParent()->getData();
                foreach ($delivery->getTasks() as $task) {
                    if ($task->getType() === Task::TYPE_PICKUP) {
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
                        $event->setData($task);
                    }
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) use ($options) {

                $form = $event->getForm();
                $delivery = $form->getData();

                if (null !== $delivery->getId()) {
                    $form->remove('pricingRuleSet');
                } else if ($options['pricing_rule_set']) {
                    $form
                        ->get('pricingRuleSet')
                        ->setData($options['pricing_rule_set']);
                }
            }
        );

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

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

        // Make sure legacy "date" field is set
        $builder->get('dropoff')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $delivery = $event->getForm()->getParent()->getData();
            $data = $event->getData();
            $delivery->setDate(new \DateTime($data['doneBefore']));
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Delivery::class,
            'pricing_rule_set' => null,
        ));
    }
}
