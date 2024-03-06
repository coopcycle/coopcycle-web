<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Package\PackageWithQuantity;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Service\TaskManager;
use AppBundle\Translation\DatePeriodFormatter;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class TaskType extends AbstractType
{
    protected $datePeriodFormatter;
    protected $locale;

    public function __construct(DatePeriodFormatter $datePeriodFormatter, string $locale)
    {
        $this->datePeriodFormatter = $datePeriodFormatter;
        $this->locale = $locale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $addressBookOptions = [
            'label' => 'form.task.address.label',
            'with_addresses' => $options['with_addresses'],
            'with_remember_address' => $options['with_remember_address'],
            'with_address_props' => $options['with_address_props'],
        ];

        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Pickup' => Task::TYPE_PICKUP,
                    'Dropoff' => Task::TYPE_DROPOFF,
                ],
                'expanded' => true,
                'multiple' => false,
                'disabled' => !$options['can_edit_type']
            ])
            ->add('address', AddressBookType::class, $addressBookOptions)
            ->add('comments', TextareaType::class, [
                'label' => 'form.task.comments.label',
                'required' => false,
                'attr' => ['rows' => '2', 'placeholder' => 'form.task.comments.placeholder']
            ])
        ->add('imported_from');

        if (null !== $options['with_time_slot']
        && null !== $options['with_time_slots']
        && count($options['with_time_slots']) > 1) {

            $iterator = $options['with_time_slots']->getIterator();
            $iterator->uasort(function (TimeSlot $a, TimeSlot $b) {
                return ($a->getName() < $b->getName()) ? -1 : 1;
            });
            $choices = new ArrayCollection(iterator_to_array($iterator));

            $builder
                ->add('switchTimeSlot', EntityType::class, [
                    'class' => TimeSlot::class,
                    'choices' => $choices,
                    'choice_label' => 'name',
                    'choice_attr' => function($choice, $key, $value) {

                        $choiceLoader = new TimeSlotChoiceLoader(
                            $choice,
                            $this->locale
                        );
                        $choiceList = $choiceLoader->loadChoiceList();

                        $choices = [];
                        foreach ($choiceList->getChoices() as $choice) {
                            $choices[] = [
                                'label' => $this->datePeriodFormatter->toHumanReadable($choice->toDatePeriod()),
                                'value' => (string) $choice,
                            ];
                        }

                        return [
                            'data-choices' => json_encode($choices),
                        ];
                    },
                    'label' => false,
                    'required' => true,
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'data' => $options['with_time_slot'],
                ]);

            // https://symfony.com/doc/5.4/form/dynamic_form_modification.html#form-events-submitted-data
            $builder->get('switchTimeSlot')->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event) {

                    $parentForm = $event->getForm()->getParent();
                    $timeSlot = $event->getForm()->getData();

                    $timeSlotOptions = [
                        'time_slot' => $timeSlot,
                        'label' => 'form.delivery.time_slot.label',
                        'mapped' => false
                    ];

                    $parentForm
                        ->add('timeSlot', TimeSlotChoiceType::class, $timeSlotOptions);
                }
            );
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $task = $event->getData();

            if ($form->has('switchTimeSlot') && null !== $task && null !== $task->getId()) {
                $form->remove('switchTimeSlot');
            }

            if (null !== $options['with_time_slot']) {

                $timeSlotOptions = [
                    'time_slot' => $options['with_time_slot'],
                    'label' => 'form.delivery.time_slot.label',
                    'mapped' => false
                ];

                if (null !== $task && null !== $task->getId()) {
                    $timeSlotOptions['disabled'] = true;
                    $timeSlotOptions['data'] = TimeSlotChoice::fromTask($task);
                }

                $form
                    ->add('timeSlot', TimeSlotChoiceType::class, $timeSlotOptions);

            } else {
                $form
                    ->add('doneAfter', DateType::class, [
                        'widget' => 'single_text',
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                        'required' => false,
                        'html5' => false,
                    ])
                    ->add('doneBefore', DateType::class, [
                        'widget' => 'single_text',
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                        'required' => true,
                        'html5' => false,
                    ]);
            }
        });

        if ($options['with_tags']) {
            $builder->add('tagsAsString', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'adminDashboard.tags.title'
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $task = $event->getData();

            $taskType = null !== $task ? $task->getType() : Task::TYPE_DROPOFF;

            if (Task::TYPE_DROPOFF === $taskType) {
                if ($options['with_doorstep']) {
                    $form
                        ->add('doorstep', CheckboxType::class, [
                            'label' => 'form.task.dropoff.doorstep.label',
                            'required' => false,
                        ]);
                }
            }
        });

        if ($builder->has('tagsAsString')) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

                $form = $event->getForm();
                $task = $event->getData();

                if (null === $task) {
                    return;
                }

                $form->get('tagsAsString')->setData(implode(' ', $task->getTags()));
            });

            $builder->get('tagsAsString')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

                $task = $event->getForm()->getParent()->getData();

                if (null === $task) {
                    return;
                }

                $tagsAsString = $event->getData();
                $tags = explode(' ', $tagsAsString);

                $task->setTags($tags);
            });
        }

        if (null !== $options['with_package_set']) {

            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

                $form = $event->getForm();
                $task = $event->getData();

                // Because we are using a collection of forms, $task may be NULL
                // When $task == NULL, it means it's an additional task
                // In this case, we add the "packages" field anyways,
                // to avoid the error "This form should not contain extra fields"
                if (null === $task || $task->getType() === Task::TYPE_DROPOFF) {

                    $data = [];

                    if ($task && $task->hasPackages()) {
                        foreach ($task->getPackages() as $wrappedPackage) {
                            $pwq = new PackageWithQuantity($wrappedPackage->getPackage());
                            $pwq->setQuantity($wrappedPackage->getQuantity());
                            $data[] = $pwq;
                        }
                    }

                    $form->add('packages', CollectionType::class, [
                        'entry_type' => PackageWithQuantityType::class,
                        'entry_options' => [
                            'label' => false,
                            'package_set' => $options['with_package_set'],
                        ],
                        'label' => 'form.delivery.packages.label',
                        'mapped' => false,
                        'allow_add' => true,
                        'allow_delete' => true,
                        'attr' => [
                            'data-packages-required' => var_export($options['with_packages_required'], true),
                        ],
                        'prototype_name' => '__package__'
                    ]);

                    $form->get('packages')->setData($data);
                }
            });
        }

        // Add weight field if needed
        if (true === $options['with_weight']) {

            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
                $form = $event->getForm();
                $task = $event->getData();

                // Because we are using a collection of forms, $task may be NULL
                // When $task == NULL, it means it's an additional task
                // In this case, we add the "weight" field anyways,
                // to avoid the error "This form should not contain extra fields"
                if (null === $task || $task->getType() === Task::TYPE_DROPOFF) {
                    $form
                        ->add('weight', NumberType::class, [
                            'required' => $options['with_weight_required'],
                            'html5' => true,
                            'label' => 'form.delivery.weight.label',
                            'attr'  => array(
                                'min'  => 0,
                                'step' => 0.5,
                            ),
                        ]);

                    if (null !== $task && null !== $task->getId()) {
                        $weight = null !== $task->getWeight() ? $task->getWeight() / 1000 : 0;
                        $form->get('weight')->setData($weight);
                    }
                }

            });
        }

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            if ($form->has('timeSlot') && !$form->get('timeSlot')->isDisabled()) {
                $choice = $form->get('timeSlot')->getData();
                if ($choice) {
                    $choice->applyToTask($task);
                }
            }

            if ($form->has('packages')) {

                $packages = $form->get('packages')->getData();

                $originalPackages = new ArrayCollection();
                foreach ($task->getPackages() as $p) {
                    $originalPackages->add($p->getPackage());
                }

                $hash = new \SplObjectStorage();

                foreach ($packages as $packageWithQuantity) {
                    $package = $packageWithQuantity->getPackage();
                    if (!$hash->contains($package)) {
                        $hash[$package] = 0;
                    }
                    $hash[$package] = $hash[$package] + $packageWithQuantity->getQuantity();
                }

                foreach ($hash as $package) {
                    $quantity = $hash[$package];
                    $task->setQuantityForPackage($package, $quantity);
                }

                foreach ($originalPackages as $originalPackage) {
                    if (!$hash->contains($originalPackage)) {
                        $task->removePackage($originalPackage);
                    }
                }
            }

            if ($form->has('weight')) {
                $weightK = $form->get('weight')->getData();
                $weight = $weightK * 1000;
                $task->setWeight($weight);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
            'can_edit_type' => true,
            'with_tags' => true,
            'with_addresses' => [],
            'with_doorstep' => false,
            'with_remember_address' => false,
            'with_time_slot' => null,
            'with_time_slots' => null,
            'with_address_props' => false,
            'with_package_set' => null,
            'with_packages_required' => false,
            'with_weight' => true,
            'with_weight_required' => false,
        ));

        $resolver->setAllowedTypes('with_time_slot', ['null', TimeSlot::class]);
        $resolver->setAllowedTypes('with_package_set', ['null', PackageSet::class]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $taskType = strtolower($view->vars['value']->getType());

        $view->vars['label'] = sprintf('form.delivery.%s.label', $taskType);

        // Custom label based on task type
        $view->children['address']->vars['label'] =
            sprintf('form.delivery.%s.label', $taskType);

        $streetAddress = $view->children['address']->children['newAddress']->children['streetAddress'];

        // Custom placeholder based on task type
        $streetAddress->vars['attr'] = array_merge(
            $streetAddress->vars['attr'] ?? [],
            [ 'placeholder' => sprintf('form.delivery.%s.address_placeholder', $taskType) ]
        );
    }
}
