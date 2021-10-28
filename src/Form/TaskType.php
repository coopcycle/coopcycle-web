<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Service\TaskManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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

class TaskType extends AbstractType
{
    private $country;

    public function __construct(string $country)
    {
        $this->country = $country;
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
            ]);

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

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            if ($form->has('timeSlot') && !$form->get('timeSlot')->isDisabled()) {
                $choice = $form->get('timeSlot')->getData();
                if ($choice) {
                    $choice->applyToTask($task);
                }
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
            'with_address_props' => false,
        ));

        $resolver->setAllowedTypes('with_time_slot', ['null', TimeSlot::class]);
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
