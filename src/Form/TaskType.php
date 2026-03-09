<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package\PackageWithQuantity;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceType;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('address', AddressType::class, [
                'with_widget' => true,
            ])
            ->add('comments', TextareaType::class, [
                'label' => 'form.task.comments.label',
                'required' => false,
                'attr' => ['rows' => '2', 'placeholder' => 'form.task.comments.placeholder']
            ])
            ->add('imported_from');

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $task = $event->getData();

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

                $form->add('timeSlot', TimeSlotChoiceType::class, $timeSlotOptions);

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

        if (null !== $options['with_package_set']) {

            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

                $form = $event->getForm();
                $task = $event->getData();

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

        if (true === $options['with_weight']) {

            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
                $form = $event->getForm();
                $task = $event->getData();

                if (null === $task || $task->getType() === Task::TYPE_DROPOFF) {
                    $form->add('weight', NumberType::class, [
                        'required' => $options['with_weight_required'],
                        'html5' => true,
                        'label' => 'form.delivery.weight.label',
                        'attr'  => [
                            'min'  => 0,
                            'step' => 0.5,
                        ],
                    ]);

                    if (null !== $task) {
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
        $resolver->setDefaults([
            'data_class' => Task::class,
            'with_time_slot' => null,
            'with_package_set' => null,
            'with_packages_required' => false,
            'with_weight' => true,
            'with_weight_required' => false,
        ]);

        $resolver->setAllowedTypes('with_time_slot', ['null', TimeSlot::class]);
        $resolver->setAllowedTypes('with_package_set', ['null', PackageSet::class]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $taskType = strtolower($view->vars['value']->getType());

        $view->vars['label'] = sprintf('form.delivery.%s.label', $taskType);

        $view->children['address']->vars['label'] =
            sprintf('form.delivery.%s.label', $taskType);

        // FIXME Doesn't work, placeholder stays the same
        $streetAddress = $view->children['address']->children['streetAddress'];
        $streetAddress->vars['attr'] = array_merge(
            $streetAddress->vars['attr'] ?? [],
            [ 'placeholder' => sprintf('form.delivery.%s.address_placeholder', $taskType) ]
        );
    }
}
